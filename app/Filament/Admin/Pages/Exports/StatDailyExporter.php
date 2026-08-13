<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages\Exports;

use App\Models\StatDaily;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

/**
 * Exports whichever `stat_dailies` rows the analytics report page's active
 * filter set currently resolves to — the same data its table renders.
 */
final class StatDailyExporter extends Exporter
{
    protected static ?string $model = StatDaily::class;

    /** @return list<ExportColumn> */
    public static function getColumns(): array
    {
        return [
            ExportColumn::make('date'),
            ExportColumn::make('kind'),
            ExportColumn::make('subject_type'),
            ExportColumn::make('subject_id'),
            ExportColumn::make('contact_channel_type_id'),
            ExportColumn::make('territory_id'),
            ExportColumn::make('country_id'),
            ExportColumn::make('locale'),
            ExportColumn::make('count'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return trans_choice(
            'panel.analytics_report.notifications.export_completed',
            $export->successful_rows,
            ['count' => number_format($export->successful_rows)],
        );
    }
}
