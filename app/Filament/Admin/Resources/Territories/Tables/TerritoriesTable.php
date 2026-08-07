<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Territories\Tables;

use App\Models\Country;
use App\Models\Territory;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TerritoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('panel.territories.columns.name'))
                    ->getStateUsing(fn (Territory $record): string => $record->name ?? "#{$record->id}")
                    ->searchable(query: fn ($query, string $search) => $query
                        ->whereHas('translations', fn ($translations) => $translations->where('name', 'ilike', "%{$search}%")))
                    ->sortable(),

                TextColumn::make('level.singular_name')
                    ->label(__('panel.territories.columns.level'))
                    ->getStateUsing(fn (Territory $record): string => $record->level->singular_name ?? '—'),

                TextColumn::make('country.code')
                    ->label(__('panel.territories.columns.country'))
                    ->sortable(),

                TextColumn::make('parent.name')
                    ->label(__('panel.territories.columns.parent'))
                    // Larastan types every BelongsTo relation as non-nullable
                    // regardless of the foreign key's own nullability, so the
                    // null case — a country root, which genuinely has no
                    // parent — is checked against `parent_id` directly rather
                    // than with a nullsafe operator on the relation itself.
                    ->getStateUsing(fn (Territory $record): string => $record->parent_id === null ? '—' : ($record->parent->name ?? '—')),

                TextColumn::make('display_order')
                    ->label(__('panel.territories.form.display_order'))
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label(__('panel.territories.columns.active'))
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('country_id')
                    ->label(__('panel.territories.columns.country'))
                    ->options(fn (): array => Country::query()->orderBy('display_order')->pluck('code', 'id')->all()),

                SelectFilter::make('is_active')
                    ->label(__('panel.territories.columns.active'))
                    ->options([
                        '1' => __('panel.territories.status.active'),
                        '0' => __('panel.territories.status.inactive'),
                    ]),
            ])
            ->recordActions([EditAction::make()])
            ->defaultSort('display_order');
    }
}
