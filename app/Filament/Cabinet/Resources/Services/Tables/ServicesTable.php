<?php

declare(strict_types=1);

namespace App\Filament\Cabinet\Resources\Services\Tables;

use App\Models\Object_;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Always exactly one row — Filament's own tenancy scopes this resource's
 * query to the current tenant object's own primary key, mirroring
 * `ObjectsTable`'s identical reasoning. Still a real table rather than a
 * bespoke page: it is what gives `EditAction` the URL every other cabinet
 * screen's own "manage services" link points at.
 */
class ServicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns(self::columns())
            ->recordActions([EditAction::make()]);
    }

    /** @return array<TextColumn> */
    private static function columns(): array
    {
        return [
            TextColumn::make('name')
                ->label(__('panel.cabinet.services.columns.name'))
                ->getStateUsing(fn (Object_ $record): string => $record->name ?? "#{$record->id}"),

            TextColumn::make('amenities_count')
                ->label(__('panel.cabinet.services.columns.selected_count')),
        ];
    }
}
