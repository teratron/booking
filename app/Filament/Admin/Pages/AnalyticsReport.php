<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Pages\Exports\StatDailyExporter;
use App\Models\Banner;
use App\Models\Country;
use App\Models\Language;
use App\Models\ObjectType;
use App\Models\Territory;
use App\Models\User;
use App\Services\Analytics\AnalyticsReportingService;
use App\Services\Analytics\PortalReportingService;
use BackedEnum;
use Filament\Actions\ExportAction;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * The rollup report `stat_dailies` exists to serve: period-scoped counts per
 * interaction kind, filterable by every dimension the specification names,
 * with the underlying rows exportable through the same filter set. Each
 * figure is computed on demand by {@see AnalyticsReportingService} against
 * the aggregate tier only — never against raw `stat_events` — so the page
 * stays cheap regardless of retention depth.
 *
 * @property-read Table $table
 */
class AnalyticsReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.admin.pages.analytics-report';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static string|UnitEnum|null $navigationGroup = 'analytics';

    public ?string $periodFrom = null;

    public ?string $periodUntil = null;

    public ?int $objectId = null;

    public ?int $bannerId = null;

    public ?int $territoryId = null;

    public ?int $countryId = null;

    public ?int $objectTypeId = null;

    public ?string $locale = null;

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->can('analytics.view');
    }

    public static function getNavigationLabel(): string
    {
        return __('panel.analytics_report.title');
    }

    public function getTitle(): string
    {
        return __('panel.analytics_report.title');
    }

    /** @return array<string, int> */
    public function summary(): array
    {
        return app(AnalyticsReportingService::class)->summary($this->filters());
    }

    /**
     * @return array{
     *     most_viewed_objects: list<array{objectId: int, name: string, views: int}>,
     *     most_popular_categories: list<array{objectTypeId: int, name: string, views: int}>,
     *     banner_click_through_rate: float,
     *     new_owner_count: int,
     *     new_object_count: int,
     *     bump_count: int,
     *     published_promotion_count: int,
     *     pending_moderation_count: int,
     * }
     */
    public function derivedFigures(): array
    {
        return app(PortalReportingService::class)->derivedFigures($this->filters());
    }

    /** @return array<int, string> */
    public function countries(): array
    {
        return Country::query()->get()
            ->mapWithKeys(fn (Country $country): array => [$country->id => $country->code])
            ->all();
    }

    /** @return array<int, string> */
    public function territories(): array
    {
        return Territory::query()->with('translations')->get()
            ->mapWithKeys(fn (Territory $territory): array => [$territory->id => $territory->name ?? "#{$territory->id}"])
            ->all();
    }

    /** @return array<int, string> */
    public function objectTypes(): array
    {
        return ObjectType::query()->with('translations')->get()
            ->mapWithKeys(fn (ObjectType $type): array => [$type->id => $type->name ?? $type->key])
            ->all();
    }

    /** @return array<string, string> */
    public function languages(): array
    {
        return Language::query()->where('is_active', true)->orderBy('display_order')
            ->pluck('short_label', 'code')->all();
    }

    /** @return array<int, string> */
    public function banners(): array
    {
        return Banner::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function table(Table $table): Table
    {
        $filters = $this->filters();

        return $table
            ->query(fn (): Builder => app(AnalyticsReportingService::class)->scopedQuery($filters))
            ->columns([
                TextColumn::make('date')
                    ->label(__('panel.analytics_report.columns.date'))
                    ->date()
                    ->sortable(),

                TextColumn::make('kind')
                    ->label(__('panel.analytics_report.columns.kind'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("panel.analytics_report.kinds.{$state}"))
                    ->sortable(),

                TextColumn::make('subject_type')
                    ->label(__('panel.analytics_report.columns.subject_type'))
                    ->formatStateUsing(fn (string $state): string => class_basename($state)),

                TextColumn::make('subject_id')
                    ->label(__('panel.analytics_report.columns.subject_id')),

                TextColumn::make('territory_id')
                    ->label(__('panel.analytics_report.columns.territory'))
                    ->placeholder('—'),

                TextColumn::make('locale')
                    ->label(__('panel.analytics_report.columns.language'))
                    ->placeholder('—'),

                TextColumn::make('count')
                    ->label(__('panel.analytics_report.columns.count'))
                    ->sortable(),
            ])
            ->defaultSort('date', 'desc');
    }

    /** @return list<ExportAction> */
    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make()
                ->label(__('panel.analytics_report.actions.export'))
                ->exporter(StatDailyExporter::class)
                ->authorize(fn (): bool => $this->actor()?->can('analytics.export') ?? false),
        ];
    }

    /** @return array{period_from?: string, period_until?: string, object_id?: int, banner_id?: int, territory_id?: int, country_id?: int, object_type_id?: int, locale?: string} */
    private function filters(): array
    {
        $filters = array_filter([
            'period_from' => $this->periodFrom,
            'period_until' => $this->periodUntil,
            'object_id' => $this->objectId,
            'banner_id' => $this->bannerId,
            'territory_id' => $this->territoryId,
            'country_id' => $this->countryId,
            'object_type_id' => $this->objectTypeId,
            'locale' => $this->locale,
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        /** @var array{period_from?: string, period_until?: string, object_id?: int, banner_id?: int, territory_id?: int, country_id?: int, object_type_id?: int, locale?: string} $filters */
        return $filters;
    }

    private function actor(): ?User
    {
        $user = Filament::auth()->user();

        return $user instanceof User ? $user : null;
    }
}
