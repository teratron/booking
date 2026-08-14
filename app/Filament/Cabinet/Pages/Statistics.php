<?php

declare(strict_types=1);

namespace App\Filament\Cabinet\Pages;

use App\Models\Object_;
use App\Services\Cabinet\ObjectStatisticsService;
use App\Support\Cabinet\ObjectStatisticsSummary;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * The owner cabinet's dedicated statistics screen for the currently selected
 * object (this panel's Filament tenant) — all-time page views, photo views,
 * the per-channel contact-click breakdown, the traffic-source breakdown, and
 * the favorite count. All-time only in this release: no date-range picker.
 */
class Statistics extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected string $view = 'filament.cabinet.pages.statistics';

    public static function getNavigationLabel(): string
    {
        return __('panel.cabinet.statistics.title');
    }

    public function getTitle(): string
    {
        return __('panel.cabinet.statistics.title');
    }

    /**
     * The current tenant's statistics read model, or null with no tenant
     * bound — navigation and other panel chrome can render this page before
     * tenant resolution runs, so this must not throw in that case.
     */
    public function summary(): ?ObjectStatisticsSummary
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Object_) {
            return null;
        }

        return app(ObjectStatisticsService::class)->summarize($tenant);
    }
}
