<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BannerSlots\Tables;

use App\Models\BannerSlot;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class BannerSlotsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key')
                    ->label(__('panel.banner_slots.form.key'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label(__('panel.banner_slots.form.name'))
                    ->getStateUsing(fn (BannerSlot $record): string => $record->name ?? "#{$record->id}"),

                TextColumn::make('surfaces')
                    ->label(__('panel.banner_slots.form.surfaces'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("panel.banner_slots.form.surface_options.{$state}")),

                IconColumn::make('is_active')
                    ->label(__('panel.banner_slots.columns.active'))
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('panel.banner_slots.columns.active')),
            ])
            ->recordActions([EditAction::make()])
            ->defaultSort('key');
    }
}
