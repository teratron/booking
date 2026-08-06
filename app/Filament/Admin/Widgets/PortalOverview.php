<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Models\User;
use App\Services\Dashboard\DashboardMetrics;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * What the portal currently holds and what is waiting for someone.
 *
 * Every figure is scoped to the viewer's grants. A headline count that
 * ignores scope tells a country administrator exactly how large the countries
 * they cannot see are, which is the disclosure the list narrowing exists to
 * prevent.
 */
class PortalOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    // Rendered inline rather than deferred behind a placeholder. The figures
    // are cached aggregates, so the second round trip buys nothing, and a
    // widget that arrives after the page cannot be reasoned about from the
    // response — including by the test that proves the finance block is
    // absent rather than hidden.
    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->can('object.view');
    }

    /** @return array<Stat> */
    protected function getStats(): array
    {
        $user = Filament::auth()->user();

        if (! $user instanceof User) {
            return [];
        }

        $metrics = app(DashboardMetrics::class);
        $operational = $metrics->operational($user);
        $registries = $metrics->registries();

        return [
            Stat::make(__('panel.dashboard.objects_total'), (string) $operational['total'])
                ->description(__('panel.dashboard.objects_breakdown', [
                    'published' => $operational['published'],
                    'hidden' => $operational['hidden'],
                    'archived' => $operational['archived'],
                ])),

            Stat::make(__('panel.dashboard.pending_moderation'), (string) $operational['pending_moderation'])
                ->color($operational['pending_moderation'] > 0 ? 'warning' : 'gray'),

            Stat::make(__('panel.dashboard.reporting_vacancies'), (string) $operational['reporting_vacancies']),

            Stat::make(__('panel.dashboard.owners'), (string) $registries['owners'])
                ->description(__('panel.dashboard.geography_breakdown', [
                    'countries' => $registries['countries'],
                    'territories' => $registries['territories'],
                ])),
        ];
    }
}
