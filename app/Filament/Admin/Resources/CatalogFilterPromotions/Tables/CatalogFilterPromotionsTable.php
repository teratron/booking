<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\CatalogFilterPromotions\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CatalogFilterPromotionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('signature')
                    ->label(__('panel.catalog_filter_promotions.form.signature'))
                    ->searchable()
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label(__('panel.catalog_filter_promotions.form.active'))
                    ->boolean()
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label(__('panel.catalog_filter_promotions.columns.updated_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('panel.catalog_filter_promotions.form.active')),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->defaultSort('updated_at', 'desc');
    }
}
