<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\ApiClient;
use App\Models\ApiToken;
use App\Models\Banner;
use App\Models\Country;
use App\Models\Language;
use App\Models\ObjectType;
use App\Models\Territory;
use App\Policies\AuditPolicy;
use App\Policies\BackupPolicy;
use App\Services\Advertising\BannerSelectionService;
use App\Services\Authorization\CabinetAccessResolver;
use App\Services\Authorization\ScopeAuthorizer;
use App\Services\Localization\DatabaseOverlayLoader;
use App\Services\Localization\LanguageRegistry;
use App\Services\Settings\SettingsRepository;
use Astrotomic\Translatable\Locales;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Translation\Loader;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;
use Override;
use OwenIt\Auditing\Models\Audit;
use Spatie\Backup\BackupDestination\Backup;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    #[Override]
    public function register(): void
    {
        $this->app->singleton(LanguageRegistry::class);

        // Singleton specifically so ScopeAuthorizer's internal per-request
        // grant memo (see its own docblock) actually spans the whole
        // request — every registered resource's navigation visibility check
        // resolves through it once per page render.
        $this->app->singleton(ScopeAuthorizer::class);

        // Same rationale, cabinet side: a Policy check and the tenant query
        // scoping it backs can otherwise ask the same (user, object,
        // permission) question twice in one request.
        $this->app->singleton(CabinetAccessResolver::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // N+1 detection: lazy-loading, missing attributes, and silently
        // discarded mass-assignment attempts throw outside production instead
        // of degrading performance unnoticed. This is the discipline §5.9
        // calls "fails the test run rather than warning" — a warning in a
        // passing suite is a warning nobody reads.
        Model::shouldBeStrict(! $this->app->isProduction());

        // `Audit` is a vendor model this project does not own, so its policy
        // is bound the classic way rather than via a `#[UsePolicy]` attribute.
        Gate::policy(Audit::class, AuditPolicy::class);

        // Same reasoning as `Audit` above: Spatie's own backup artefact type
        // is not an `App\Models` class, so its restore ability is bound
        // explicitly rather than discovered by naming convention.
        Gate::policy(Backup::class, BackupPolicy::class);

        // Swaps in this project's own personal-access-token model so every
        // token Sanctum issues or resolves carries the revocation and rate-
        // limit columns {@see ApiToken} adds on top of the vendor schema.
        Sanctum::usePersonalAccessTokenModel(ApiToken::class);

        // Must run before anything resolves the `translator` singleton —
        // astrotomic's own Locales class (built by syncTranslatableLocales()
        // below) depends on TranslatorContract, and once resolved a
        // Translator instance keeps whatever loader it was built with; a
        // later extend() of `translation.loader` would no longer reach it.
        $this->overlayInterfaceCatalogFromDatabase();
        $this->resolveTranslationFallbackFromPrimaryLanguage();
        $this->syncTranslatableLocales();
        $this->invalidateBannerSelectionCacheOnWrite();
        $this->invalidatePublicShellCacheOnWrite();
        $this->registerApiTokenRateLimiter();
    }

    /**
     * The public API's own named limiter — a token's `rate_limit_per_minute`
     * when set, else the portal-wide `api.default_rate_limit_per_minute`
     * setting. Keyed by token id, never by IP: two consumers behind the same
     * NAT or proxy must not share one budget, and a single consumer rotating
     * IPs must not escape theirs.
     */
    private function registerApiTokenRateLimiter(): void
    {
        RateLimiter::for('api-token', function (Request $request): Limit {
            // Nested inside the `auth:sanctum` group in routes/api_v1.php,
            // so the resolved actor here is always the token's own
            // ApiClient — the same reasoning TokenController's identical
            // cast documents.
            /** @var ApiClient $client */
            $client = $request->user();
            $token = $client->currentAccessToken();

            $perMinute = $token instanceof ApiToken && $token->rate_limit_per_minute !== null
                ? $token->rate_limit_per_minute
                : (int) $this->app->make(SettingsRepository::class)->get('api.default_rate_limit_per_minute');

            return Limit::perMinute($perMinute)->by($token->id);
        });
    }

    /**
     * Registers every language row — active or not — with
     * `astrotomic/laravel-translatable`'s locale registry. The package
     * expects its `locales` config array to name every valid locale in code,
     * but this project's language count is runtime data, never hard-coded;
     * reconciled by keeping the config at its minimal non-empty placeholder
     * and appending the real set here, from the table that is the actual
     * source of truth. Guarded against a fresh install or `migrate:fresh`,
     * where this boots before the table exists.
     *
     * Reads `is_primary` alongside `code` in the same query and seeds
     * `LanguageRegistry` with it — the translation fallback resolver would
     * otherwise run a second, near-identical query of its own the first
     * time a string is translated.
     */
    private function syncTranslatableLocales(): void
    {
        if (! Schema::hasTable('languages')) {
            return;
        }

        $locales = $this->app->make(Locales::class);

        foreach (DB::table('languages')->get(['code', 'is_primary']) as $language) {
            $locales->add($language->code);

            if ($language->is_primary) {
                $this->app->make(LanguageRegistry::class)->seed($language->code);
            }
        }
    }

    /**
     * Wraps the framework's own file loader so every catalog load also
     * checks `interface_catalog_overrides` for an administrator override —
     * see `App\Services\Localization\DatabaseOverlayLoader`. Guarded the
     * same way `syncTranslatableLocales()` is: the table does not exist yet
     * during the migration that creates it.
     */
    private function overlayInterfaceCatalogFromDatabase(): void
    {
        if (! Schema::hasTable('interface_catalog_overrides')) {
            return;
        }

        $this->app->extend('translation.loader', fn (Loader $loader): DatabaseOverlayLoader => new DatabaseOverlayLoader($loader));
    }

    /**
     * A translation lookup falls back to the current primary language, not
     * the static `config('app.fallback_locale')` value — which language is
     * primary is administrator-editable data, not a build-time constant.
     * `determineLocalesUsing` is the translator's own extension point for
     * this: it receives the default `[requested, fallback]` pair and returns
     * the list actually tried, in order.
     */
    private function resolveTranslationFallbackFromPrimaryLanguage(): void
    {
        Lang::determineLocalesUsing(function (array $locales): array {
            $requested = $locales[0] ?? config('app.fallback_locale');
            $primary = $this->app->make(LanguageRegistry::class)->primaryLocale();

            return array_values(array_unique(array_filter([$requested, $primary])));
        });
    }

    /**
     * `Banner` has no dedicated write-path service of its own — it is
     * created and edited directly through the admin resource's Filament
     * pages — so this is the one place that reliably observes every write.
     * A slot change on update invalidates both the slot the banner left and
     * the one it now belongs to, since each caches its own selection
     * independently.
     */
    private function invalidateBannerSelectionCacheOnWrite(): void
    {
        $invalidate = function (Banner $banner): void {
            $service = $this->app->make(BannerSelectionService::class);
            $service->invalidateSlot((int) $banner->banner_slot_id);

            $originalSlotId = $banner->getOriginal('banner_slot_id');

            if ($originalSlotId !== null && (int) $originalSlotId !== (int) $banner->banner_slot_id) {
                $service->invalidateSlot((int) $originalSlotId);
            }
        };

        Banner::saved($invalidate);
        Banner::deleted($invalidate);
        Banner::restored($invalidate);
    }

    /**
     * `App\Services\Shell\PublicShellDataProvider` caches the navigation,
     * language, and country switcher data the public shell renders on every
     * page — a write to any registry it reads from must invalidate that
     * cache, or an administrator adding an object type, activating a
     * language, or activating a country would not see it appear until the
     * cache's own TTL expires.
     */
    private function invalidatePublicShellCacheOnWrite(): void
    {
        $flush = static function (): void {
            Cache::tags(['public-shell'])->flush();
        };

        ObjectType::saved($flush);
        ObjectType::deleted($flush);
        Language::saved($flush);
        Language::deleted($flush);
        Country::saved($flush);
        Country::deleted($flush);
        Territory::saved($flush);
        Territory::deleted($flush);
    }
}
