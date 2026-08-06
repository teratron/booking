<?php

declare(strict_types=1);

namespace App\Providers;

use Astrotomic\Translatable\Locales;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Override;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    #[Override]
    public function register(): void
    {
        //
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

        $this->syncTranslatableLocales();
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
     */
    private function syncTranslatableLocales(): void
    {
        if (! Schema::hasTable('languages')) {
            return;
        }

        $locales = $this->app->make(Locales::class);

        foreach (DB::table('languages')->pluck('code') as $code) {
            $locales->add($code);
        }
    }
}
