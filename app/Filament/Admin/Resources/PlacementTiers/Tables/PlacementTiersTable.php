<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PlacementTiers\Tables;

use App\Models\PlacementTier;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PlacementTiersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('rank')
                    ->label(__('panel.placement_tiers.columns.rank'))
                    ->sortable(),

                TextColumn::make('label')
                    ->label(__('panel.placement_tiers.form.label'))
                    ->getStateUsing(fn (PlacementTier $record): string => $record->label ?? "#{$record->rank}"),

                ColorColumn::make('border_colour')
                    ->label(__('panel.placement_tiers.form.border_colour')),

                ColorColumn::make('badge_colour')
                    ->label(__('panel.placement_tiers.form.badge_colour')),

                IconColumn::make('is_active')
                    ->label(__('panel.placement_tiers.columns.active'))
                    ->boolean()
                    ->sortable(),
            ])
            ->recordActions([EditAction::make()])
            ->defaultSort('rank');
    }
}
