<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;

/**
 * Wires the Horizon dashboard behind the same door every other staff
 * surface in this codebase uses — see {@see self::gate()}.
 */
class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Register the Horizon gate.
     *
     * Deliberately not a fixed email allow-list (the package's own
     * scaffolded default): the dashboard is queue and scheduler
     * infrastructure, so it is gated the same way every other admin
     * surface in this codebase is — {@see User::canAccessPanel()}, the
     * staff panel's own permission-based front door, rather than a second,
     * independently maintained access rule that could drift from it.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function (?User $user = null): bool {
            if (! $user instanceof User) {
                return false;
            }

            $panel = Filament::getPanel('admin');

            return $user->canAccessPanel($panel);
        });
    }
}
