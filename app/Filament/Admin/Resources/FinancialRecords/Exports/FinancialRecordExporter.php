<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\FinancialRecords\Exports;

use App\Filament\Admin\Exports\Concerns\ReadsTransferableRegistry;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

/**
 * Exports the ledger over whichever rows the active filter set (period,
 * package, status) currently resolves to — the same data the table renders.
 *
 * Column names, labels, and formatting stay exactly as they were before
 * this class read the transferable data-type registry: `package.name` and
 * `responsibleStaff.name` show a related record's own display name rather
 * than the registry's raw `placement_package_id`/`responsible_staff_id`
 * foreign keys, which {@see ReadsTransferableRegistry::appendMissingRegistryColumns()}'s
 * substitution map accounts for so neither foreign key is also appended as
 * a second, redundant column.
 */
final class FinancialRecordExporter extends Exporter
{
    use ReadsTransferableRegistry;

    public static function transferableKey(): string
    {
        return 'payments';
    }

    /** @return list<ExportColumn> */
    public static function getColumns(): array
    {
        $columns = [
            ExportColumn::make('id'),
            ExportColumn::make('object_id')->label(__('panel.financial_records.form.object')),
            ExportColumn::make('banner_id')->label(__('panel.financial_records.form.banner')),
            ExportColumn::make('service')->label(__('panel.financial_records.form.service')),
            ExportColumn::make('package.name')->label(__('panel.financial_records.form.package')),
            ExportColumn::make('amount')->label(__('panel.financial_records.form.amount')),
            ExportColumn::make('currency')->label(__('panel.financial_records.form.currency')),
            ExportColumn::make('status')->label(__('panel.financial_records.form.status')),
            ExportColumn::make('paid_at')->label(__('panel.financial_records.form.paid_at')),
            ExportColumn::make('valid_from')->label(__('panel.financial_records.form.valid_from')),
            ExportColumn::make('valid_until')->label(__('panel.financial_records.form.valid_until')),
            ExportColumn::make('payment_method')->label(__('panel.financial_records.form.payment_method')),
            ExportColumn::make('document_number')->label(__('panel.financial_records.form.document_number')),
            ExportColumn::make('responsibleStaff.name')->label(__('panel.financial_records.form.responsible_staff')),
        ];

        return self::appendMissingRegistryColumns($columns, [
            'placement_package_id' => 'package.name',
            'responsible_staff_id' => 'responsibleStaff.name',
        ]);
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return trans_choice(
            'panel.financial_records.notifications.export_completed',
            $export->successful_rows,
            ['count' => number_format($export->successful_rows)],
        );
    }
}
