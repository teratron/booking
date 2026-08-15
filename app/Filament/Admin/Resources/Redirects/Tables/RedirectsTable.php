<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Redirects\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class RedirectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('locale')
                    ->label(__('panel.redirects.form.locale'))
                    ->badge()
                    ->sortable(),

                TextColumn::make('from_path')
                    ->label(__('panel.redirects.form.from_path'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('to_path')
                    ->label(__('panel.redirects.form.to_path'))
                    ->searchable(),

                IconColumn::make('is_active')
                    ->label(__('panel.redirects.form.active'))
                    ->boolean()
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label(__('panel.redirects.columns.updated_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('locale')
                    ->label(__('panel.redirects.form.locale'))
                    ->relationship('language', 'short_label'),
                TernaryFilter::make('is_active')
                    ->label(__('panel.redirects.form.active')),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->defaultSort('updated_at', 'desc');
    }
}
