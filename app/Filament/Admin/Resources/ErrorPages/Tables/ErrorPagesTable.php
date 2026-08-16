<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ErrorPages\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ErrorPagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('status_code')
                    ->label(__('panel.error_pages.form.status_code'))
                    ->formatStateUsing(fn (int $state): string => __("panel.error_pages.status_names.{$state}"))
                    ->badge()
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label(__('panel.error_pages.columns.updated_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->defaultSort('status_code');
    }
}
