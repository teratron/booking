<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Models\Country;
use App\Models\User;
use App\Services\Placement\CommerceReportingService;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * The report set for the back office — active/ending/expired placements,
 * free placements, paid bump counts, active campaigns — filterable by country
 * and period. Each figure is computed on demand by
 * {@see CommerceReportingService}; the raw rows behind any of them are one
 * click away on the ledger resource this page sits beside.
 */
class CommerceReports extends Page
{
    protected string $view = 'filament.admin.pages.commerce-reports';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'commerce';

    public ?int $countryId = null;

    public ?string $periodFrom = null;

    public ?string $periodUntil = null;

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->can('finance.view');
    }

    public static function getNavigationLabel(): string
    {
        return __('panel.commerce_reports.title');
    }

    public function getTitle(): string
    {
        return __('panel.commerce_reports.title');
    }

    /** @return array<string, int> */
    public function summary(): array
    {
        $filters = array_filter([
            'country' => $this->countryId,
            'period_from' => $this->periodFrom,
            'period_until' => $this->periodUntil,
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        /** @var array{country?: int, period_from?: string, period_until?: string} $filters */
        return app(CommerceReportingService::class)->summary($filters);
    }

    /** @return array<int, string> */
    public function countries(): array
    {
        return Country::query()->get()
            ->mapWithKeys(fn (Country $country): array => [$country->id => $country->code])
            ->all();
    }
}
