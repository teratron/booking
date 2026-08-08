<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ActionJournal\Exports;

use App\Services\Audit\AuditPresenter;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Str;
use OwenIt\Auditing\Models\Audit;

/**
 * Exports exactly the columns the journal itself renders — actor, action,
 * target, previous value, new value, timestamp, IP, device, outcome — over
 * whichever rows the active filter set on the table currently resolves to.
 */
final class ActionJournalExporter extends Exporter
{
    protected static ?string $model = Audit::class;

    /** @return list<ExportColumn> */
    public static function getColumns(): array
    {
        return [
            ExportColumn::make('created_at')->label(__('panel.action_journal.columns.occurred_at')),
            ExportColumn::make('user.name')->label(__('panel.action_journal.columns.actor')),
            ExportColumn::make('event')->label(__('panel.action_journal.columns.action')),
            ExportColumn::make('auditable_type')
                ->label(__('panel.action_journal.columns.target'))
                ->formatStateUsing(fn (?string $state): string => $state === null ? '—' : Str::headline(class_basename($state))),
            ExportColumn::make('auditable_id')->label(__('panel.action_journal.columns.target_id')),
            ExportColumn::make('old_values')
                ->label(__('panel.action_journal.columns.previous_value'))
                ->formatStateUsing(fn (mixed $state): string => $state === null ? '—' : (json_encode($state) ?: '—')),
            ExportColumn::make('new_values')
                ->label(__('panel.action_journal.columns.new_value'))
                ->formatStateUsing(fn (mixed $state): string => $state === null ? '—' : (json_encode($state) ?: '—')),
            ExportColumn::make('ip_address')->label(__('panel.action_journal.columns.ip_address')),
            ExportColumn::make('user_agent')->label(__('panel.action_journal.columns.device')),
            ExportColumn::make('event')
                ->label(__('panel.action_journal.columns.outcome'))
                ->formatStateUsing(fn (string $state): string => AuditPresenter::outcome($state)),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return trans_choice(
            'panel.action_journal.notifications.export_completed',
            $export->successful_rows,
            ['count' => number_format($export->successful_rows)],
        );
    }
}
