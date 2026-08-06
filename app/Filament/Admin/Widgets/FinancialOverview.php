<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Models\User;
use App\Services\Dashboard\DashboardMetrics;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Money figures, behind their own grant.
 *
 * Financial reads are a separate permission from every other panel read, so
 * this widget is absent from the response for an account without it — not
 * rendered and hidden. The queries behind it never run either: an
 * unauthorized read that executes and is then discarded is still a read.
 *
 * Deliberately narrow. Campaign spend, paid bumps, and extended analytics
 * belong to the commerce phase and are not shown here as zeroes, because a
 * zero that means "not built" is indistinguishable from a zero that means
 * "none" — and only the second is information.
 */
class FinancialOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 2;

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->can('financial_access');
    }

    /** @return array<Stat> */
    protected function getStats(): array
    {
        $user = Filament::auth()->user();

        if (! $user instanceof User) {
            return [];
        }

        $financial = app(DashboardMetrics::class)->financial($user);

        return [
            Stat::make(__('panel.dashboard.recorded_amount'), (string) $financial['recorded_amount'])
                ->description(__('panel.dashboard.recorded_amount_window')),

            Stat::make(__('panel.dashboard.active_placements'), (string) $financial['active_placements']),

            Stat::make(__('panel.dashboard.expiring_placements'), (string) $financial['expiring_placements'])
                ->color($financial['expiring_placements'] > 0 ? 'warning' : 'gray'),
        ];
    }
}
