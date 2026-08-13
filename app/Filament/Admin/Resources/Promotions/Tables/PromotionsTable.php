<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Promotions\Tables;

use App\Models\Promotion;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class PromotionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(__('panel.promotions.form.title'))
                    ->getStateUsing(fn (Promotion $record): string => $record->title ?? "#{$record->id}")
                    ->searchable(),

                TextColumn::make('object.name')
                    ->label(__('panel.promotions.form.object'))
                    ->sortable(),

                TextColumn::make('territory.name')
                    ->label(__('panel.promotions.form.territory'))
                    ->sortable(),

                TextColumn::make('status')
                    ->label(__('panel.promotions.form.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("panel.promotions.status.{$state}"))
                    ->sortable(),

                TextColumn::make('moderation_status')
                    ->label(__('panel.objects.columns.moderation_status'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state === null ? '—' : __("panel.promotions.moderation_status.{$state}")),

                TextColumn::make('ends_at')
                    ->label(__('panel.promotions.form.ends_at'))
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                TrashedFilter::make(),

                SelectFilter::make('status')
                    ->label(__('panel.promotions.form.status'))
                    ->options([
                        'draft' => __('panel.promotions.status.draft'),
                        'scheduled' => __('panel.promotions.status.scheduled'),
                        'published' => __('panel.promotions.status.published'),
                        'archived' => __('panel.promotions.status.archived'),
                    ]),
            ])
            ->recordActions([EditAction::make()])
            ->defaultSort('id', 'desc');
    }
}
