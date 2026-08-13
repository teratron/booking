<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\FinancialRecords\Tables;

use App\Models\FinancialRecord;
use App\Models\PlacementPackage;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FinancialRecordsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),

                TextColumn::make('subject')
                    ->label(__('panel.financial_records.form.subject_kind'))
                    ->getStateUsing(fn (FinancialRecord $record): string => $record->object_id !== null
                        ? __('panel.financial_records.form.subject_object')." #{$record->object_id}"
                        : __('panel.financial_records.form.subject_banner')." #{$record->banner_id}"),

                TextColumn::make('service')->label(__('panel.financial_records.form.service'))->searchable(),

                TextColumn::make('package.name')
                    ->label(__('panel.financial_records.form.package'))
                    ->getStateUsing(fn (FinancialRecord $record): string => $record->package instanceof PlacementPackage
                        ? ($record->package->name ?? "#{$record->package->id}")
                        : '—'),

                TextColumn::make('amount')
                    ->label(__('panel.financial_records.form.amount'))
                    ->money(fn (FinancialRecord $record): string => $record->currency)
                    ->sortable(),

                TextColumn::make('status')
                    ->label(__('panel.financial_records.form.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("panel.financial_records.status.{$state}")),

                TextColumn::make('valid_until')->label(__('panel.financial_records.form.valid_until'))->date()->sortable(),

                TextColumn::make('responsibleStaff.name')
                    ->label(__('panel.financial_records.form.responsible_staff'))
                    ->getStateUsing(fn (FinancialRecord $record): string => $record->responsibleStaff instanceof User
                        ? $record->responsibleStaff->name
                        : '—'),

                TextColumn::make('created_at')->label(__('panel.financial_records.form.paid_at'))->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('panel.financial_records.form.status'))
                    ->options([
                        'awaiting_payment' => __('panel.financial_records.status.awaiting_payment'),
                        'paid' => __('panel.financial_records.status.paid'),
                        'partially_paid' => __('panel.financial_records.status.partially_paid'),
                        'overdue' => __('panel.financial_records.status.overdue'),
                        'cancelled' => __('panel.financial_records.status.cancelled'),
                        'granted_free' => __('panel.financial_records.status.granted_free'),
                    ]),
                SelectFilter::make('placement_package_id')
                    ->label(__('panel.financial_records.form.package'))
                    ->relationship('package', 'id'),
                Filter::make('period')
                    ->schema([
                        DatePicker::make('from')->label(__('panel.financial_records.filters.from')),
                        DatePicker::make('until')->label(__('panel.financial_records.filters.until')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->recordActions([EditAction::make()])
            ->defaultSort('created_at', 'desc');
    }
}
