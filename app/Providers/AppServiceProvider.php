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
use App\Models\User;
use App\Policies\AuditPolicy;
use App\Policies\BackupPolicy;
use App\Services\Advertising\BannerSelectionService;
use App\Services\Authorization\CabinetAccessResolver;
use App\Services\Authorization\ScopeAuthorizer;
use App\Services\Localization\DatabaseOverlayLoader;
use App\Services\Localization\LanguageRegistry;
use App\Services\Modules\ModuleResolver;
use App\Services\Settings\SettingsRepository;
use App\Services\Shell\LocaleSwitchResolver;
use Astrotomic\Translatable\Locales;
use Filament\Facades\Filament;
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
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;
use Override;
use OwenIt\Auditing\Models\Audit;
use Spatie\Backup\BackupDestination\Backup;
use Throwable;

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

        // Singleton so its per-request object/territory resolution memo
        // (see its own docblock) actually spans the whole request — the
        // header's desktop and mobile language switchers each call
        // `app(LocaleSwitchResolver::class)` directly, and the page's own
        // hreflang alternates resolve a third, separately injected
        // instance; without this, each of the three re-runs the same
        // slug resolution from scratch.
        $this->app->singleton(LocaleSwitchResolver::class);

        // Same rationale again: the object page alone asks the same
        // module/context question up to eight times across independently
        // injected services (see its own docblock's per-request memo).
        $this->app->singleton(ModuleResolver::class);
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
        $this->pinGeneratedUrlsToConfiguredAppUrl();
        $this->overlayInterfaceCatalogFromDatabase();
        $this->resolveTranslationFallbackFromPrimaryLanguage();
        $this->syncTranslatableLocales();
        $this->invalidateBannerSelectionCacheOnWrite();
        $this->invalidatePublicShellCacheOnWrite();
        $this->registerApiTokenRateLimiter();
        $this->registerPulseAuthorization();
    }

    /**
     * Gates the Pulse dashboard behind the identical door every other
     * staff surface uses — {@see User::canAccessPanel()} — so
     * production-performance visibility never becomes a second,
     * independently maintained access rule that could drift from the
     * staff panel's own permission gate. Horizon's own gate
     * ({@see HorizonServiceProvider::gate()}) makes the
     * identical call for the identical reason.
     */
    private function registerPulseAuthorization(): void
    {
        Gate::define('viewPulse', function (User $user): bool {
            $panel = Filament::getPanel('admin');

            return $user->canAccessPanel($panel);
        });
    }

    /**
     * `route()`/`url()` otherwise resolve from the *incoming request's* Host
     * header and detected scheme, not `config('app.url')` — harmless on a
     * single-host dev box where the two coincide, but wrong the moment a
     * request reaches the app through anything else with a different Host
     * (a load balancer, a misconfigured client, a bare IP hit). Canonicals,
     * Open Graph tags, JSON-LD, and API response URLs must always name the
     * *configured* host, matching `sitemap.xml` (`spatie/laravel-sitemap`),
     * which already reads `config('app.url')` directly for exactly this
     * reason. `forceRootUrl()` alone is not sufficient: `UrlGenerator::
     * formatRoot()` still rewrites the forced root's own scheme to whatever
     * the *current request* resolves to (`formatScheme()`), so on a host
     * that terminates TLS upstream of the app — this project's own
     * production topology — every generated URL would still downgrade to
     * `http://` without `forceScheme()` too. Deriving the scheme from
     * `APP_URL` itself, rather than a hard-coded `'https'`, means this never
     * drifts out of sync with the value it exists to enforce, and stays
     * correct for local dev's own `http://` URL without an
     * environment-conditional branch.
     */
    private function pinGeneratedUrlsToConfiguredAppUrl(): void
    {
        $appUrl = (string) config('app.url');

        URL::forceRootUrl($appUrl);
        URL::forceScheme((string) parse_url($appUrl, PHP_URL_SCHEME));
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
        if (! $this->hasReachableTable('languages')) {
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
        if (! $this->hasReachableTable('interface_catalog_overrides')) {
            return;
        }

        $this->app->extend('translation.loader', fn (Loader $loader): DatabaseOverlayLoader => new DatabaseOverlayLoader($loader));
    }

    /**
     * `Schema::hasTable()` alone assumes a database connection can be
     * opened — true once migrations have run, false before a table exists,
     * but it throws rather than returning false when no connection can be
     * opened at all. That third state is not hypothetical: `composer
     * install`'s own `post-autoload-dump` hook runs `artisan
     * package:discover`, which boots this provider, before `.env` exists
     * anywhere the framework has ever run from a fresh checkout — CI's own
     * "Install dependencies" step included. Both call sites above need "not
     * ready yet" to mean the same thing whether the cause is a missing
     * table or a missing connection.
     */
    private function hasReachableTable(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (Throwable) {
            return false;
        }
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
