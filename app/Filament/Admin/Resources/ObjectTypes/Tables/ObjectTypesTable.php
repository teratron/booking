<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ObjectTypes\Tables;

use App\Models\ObjectType;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ObjectTypesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key')
                    ->label(__('panel.object_types.form.key'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label(__('panel.object_types.form.name'))
                    ->getStateUsing(fn (ObjectType $record): string => $record->name ?? $record->key),

                TextColumn::make('parent.key')
                    ->label(__('panel.object_types.form.parent'))
                    ->getStateUsing(fn (ObjectType $record): string => $record->parent_id === null ? '—' : ($record->parent->key ?? '—')),

                IconColumn::make('has_rooms')
                    ->label(__('panel.object_types.form.has_rooms'))
                    ->boolean(),

                IconColumn::make('has_availability_status')
                    ->label(__('panel.object_types.form.has_availability_status'))
                    ->boolean(),

                IconColumn::make('is_active')
                    ->label(__('panel.object_types.columns.active'))
                    ->boolean()
                    ->sortable(),

                TextColumn::make('display_order')
                    ->label(__('panel.object_types.form.display_order'))
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('panel.object_types.columns.active')),
            ])
            ->recordActions([EditAction::make()])
            ->defaultSort('display_order');
    }
}
