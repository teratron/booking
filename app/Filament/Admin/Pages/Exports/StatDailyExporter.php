<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages\Exports;

use App\Filament\Admin\Exports\Concerns\ReadsTransferableRegistry;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

/**
 * Exports whichever `stat_dailies` rows the analytics report page's active
 * filter set currently resolves to — the same data its table renders.
 *
 * None of these columns carries an explicit label, matching this
 * exporter's behaviour before it read the transferable data-type registry —
 * Filament's own default (a humanized form of the column name) is what a
 * staff member has always seen here. `id`, the one registry-declared
 * column this exporter never emitted, is picked up automatically by
 * {@see ReadsTransferableRegistry::appendMissingRegistryColumns()}, the
 * same way it would be for any column the `statistics` registry entry
 * gains in the future.
 */
final class StatDailyExporter extends Exporter
{
    use ReadsTransferableRegistry;

    public static function transferableKey(): string
    {
        return 'statistics';
    }

    /** @return list<ExportColumn> */
    public static function getColumns(): array
    {
        $columns = [
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

        return self::appendMissingRegistryColumns($columns);
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
