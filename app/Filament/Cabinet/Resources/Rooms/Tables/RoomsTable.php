<?php

declare(strict_types=1);

namespace App\Filament\Cabinet\Resources\Rooms\Tables;

use App\Models\Room;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The owner's room list for the currently selected object, ordered the way
 * it will read on the object's own public page.
 */
class RoomsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns(self::columns())
            ->defaultSort('display_order')
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    /** @return array<TextColumn|IconColumn> */
    private static function columns(): array
    {
        return [
            TextColumn::make('name')
                ->label(__('panel.cabinet.rooms.columns.name'))
                ->getStateUsing(fn (Room $record): string => $record->name ?? "#{$record->id}"),

            TextColumn::make('capacity')
                ->label(__('panel.cabinet.rooms.columns.capacity')),

            TextColumn::make('max_guests')
                ->label(__('panel.cabinet.rooms.columns.max_guests')),

            TextColumn::make('area_sqm')
                ->label(__('panel.cabinet.rooms.columns.area_sqm')),

            IconColumn::make('is_active')
                ->label(__('panel.cabinet.rooms.columns.is_active'))
                ->boolean(),

            TextColumn::make('display_order')
                ->label(__('panel.cabinet.rooms.columns.display_order')),
        ];
    }
}
