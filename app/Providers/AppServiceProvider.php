<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
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
    }
}
