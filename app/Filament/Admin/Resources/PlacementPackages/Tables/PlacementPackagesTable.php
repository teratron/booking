<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PlacementPackages\Tables;

use App\Models\PlacementPackage;
use App\Models\PlacementTier;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PlacementPackagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('panel.placement_packages.form.name'))
                    ->getStateUsing(fn (PlacementPackage $record): string => $record->name ?? "#{$record->id}"),

                TextColumn::make('tier.label')
                    ->label(__('panel.placement_packages.form.tier'))
                    ->getStateUsing(function (PlacementPackage $record): string {
                        $tier = $record->tier;

                        if (! $tier instanceof PlacementTier) {
                            return '—';
                        }

                        return $tier->label ?? "#{$tier->rank}";
                    }),

                TextColumn::make('objectType.key')
                    ->label(__('panel.placement_packages.form.object_type'))
                    // `object_type_id` is a genuinely nullable FK ("any category"); Larastan
                    // types every BelongsTo relation as non-nullable regardless of the column's
                    // real nullability, so the FK is checked directly rather than nullsafe-accessing
                    // the relation.
                    ->getStateUsing(fn (PlacementPackage $record): string => $record->object_type_id === null
                        ? __('panel.placement_packages.form.any_object_type')
                        : ($record->objectType->key ?? __('panel.placement_packages.form.any_object_type'))),

                TextColumn::make('price')
                    ->label(__('panel.placement_packages.form.price'))
                    ->money(fn (PlacementPackage $record): string => $record->currency)
                    ->sortable(),

                TextColumn::make('validity_days')
                    ->label(__('panel.placement_packages.form.validity_days'))
                    ->sortable(),

                IconColumn::make('bump_allowed')
                    ->label(__('panel.placement_packages.form.bump_allowed'))
                    ->boolean(),

                IconColumn::make('is_active')
                    ->label(__('panel.placement_packages.columns.active'))
                    ->boolean()
                    ->sortable(),

                TextColumn::make('display_order')
                    ->label(__('panel.placement_packages.form.display_order'))
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('placement_tier_id')
                    ->label(__('panel.placement_packages.form.tier'))
                    ->relationship('tier', 'rank'),
                TernaryFilter::make('is_active')
                    ->label(__('panel.placement_packages.columns.active')),
            ])
            ->recordActions([EditAction::make()])
            ->defaultSort('display_order');
    }
}
