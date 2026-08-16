<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ApiClients\Tables;

use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ApiClientsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('panel.api_clients.form.name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('contact')
                    ->label(__('panel.api_clients.form.contact'))
                    ->searchable()
                    ->placeholder('—'),

                IconColumn::make('is_active')
                    ->label(__('panel.api_clients.form.active'))
                    ->boolean()
                    ->sortable(),

                TextColumn::make('tokens_count')
                    ->label(__('panel.api_clients.columns.tokens'))
                    ->counts('tokens'),

                TextColumn::make('creator.name')
                    ->label(__('panel.api_clients.columns.created_by')),

                TextColumn::make('created_at')
                    ->label(__('panel.api_clients.columns.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('panel.api_clients.form.active')),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
