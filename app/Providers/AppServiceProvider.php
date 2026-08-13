<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Banner;
use App\Policies\AuditPolicy;
use App\Services\Advertising\BannerSelectionService;
use App\Services\Authorization\ScopeAuthorizer;
use App\Services\Localization\DatabaseOverlayLoader;
use App\Services\Localization\LanguageRegistry;
use Astrotomic\Translatable\Locales;
use Illuminate\Contracts\Translation\Loader;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Override;
use OwenIt\Auditing\Models\Audit;

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

        // Must run before anything resolves the `translator` singleton —
        // astrotomic's own Locales class (built by syncTranslatableLocales()
        // below) depends on TranslatorContract, and once resolved a
        // Translator instance keeps whatever loader it was built with; a
        // later extend() of `translation.loader` would no longer reach it.
        $this->overlayInterfaceCatalogFromDatabase();
        $this->resolveTranslationFallbackFromPrimaryLanguage();
        $this->syncTranslatableLocales();
        $this->invalidateBannerSelectionCacheOnWrite();
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
}
