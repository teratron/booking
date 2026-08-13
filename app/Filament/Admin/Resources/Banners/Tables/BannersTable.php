<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Banners\Tables;

use App\Models\Banner;
use App\Models\BannerSlot;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class BannersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('panel.banners.form.name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('slot.key')
                    ->label(__('panel.banners.form.slot'))
                    ->sortable(),

                TextColumn::make('advertiser')
                    ->label(__('panel.banners.form.advertiser'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('starts_at')
                    ->label(__('panel.banners.form.starts_at'))
                    ->date()
                    ->sortable(),

                TextColumn::make('ends_at')
                    ->label(__('panel.banners.form.ends_at'))
                    ->date()
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label(__('panel.banners.columns.active'))
                    ->boolean()
                    ->sortable(),

                TextColumn::make('impressions')
                    ->label(__('panel.banners.columns.impressions'))
                    ->numeric()
                    ->sortable(),

                TextColumn::make('clicks')
                    ->label(__('panel.banners.columns.clicks'))
                    ->numeric()
                    ->sortable(),

                // Computed, not stored: the ratio moves every time analytics
                // records a new event, so persisting it would mean keeping a
                // derived column in step with two others on every write.
                TextColumn::make('click_through_rate')
                    ->label(__('panel.banners.columns.click_through_rate'))
                    ->getStateUsing(fn (Banner $record): string => $record->impressions > 0
                        ? number_format($record->clicks / $record->impressions * 100, 2).'%'
                        : '—'),

                TextColumn::make('display_order')
                    ->label(__('panel.banners.form.display_order'))
                    ->sortable(),
            ])
            ->filters([
                // Banner is soft-deletable — without this filter an archived
                // banner is invisible in this list by design, not by accident.
                TrashedFilter::make(),

                SelectFilter::make('banner_slot_id')
                    ->label(__('panel.banners.form.slot'))
                    ->options(fn (): array => BannerSlot::query()
                        ->with('translations')
                        ->get()
                        ->mapWithKeys(fn (BannerSlot $slot): array => [$slot->id => $slot->name ?? $slot->key])
                        ->all()),

                TernaryFilter::make('is_active')
                    ->label(__('panel.banners.columns.active')),
            ])
            ->recordActions([EditAction::make()])
            ->defaultSort('display_order');
    }
}
