<?php

declare(strict_types=1);

namespace App\Filament\Cabinet\Resources\NewsItems\Tables;

use App\Filament\Cabinet\Resources\NewsItems\NewsItemResource;
use App\Models\NewsItem;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The owner's own news list for the currently selected object — including
 * whatever is still pending, rejected, or sent back for revision, since
 * {@see NewsItemResource::getEloquentQuery()}
 * strips the moderation scope that would otherwise hide those from the
 * owner's own cabinet.
 */
class NewsItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns(self::columns())
            ->defaultSort('created_at', 'desc')
            ->recordActions([EditAction::make()]);
    }

    /** @return list<TextColumn> */
    private static function columns(): array
    {
        return [
            TextColumn::make('title')
                ->label(__('panel.cabinet.news_items.columns.title'))
                ->getStateUsing(fn (NewsItem $record): string => $record->title ?? "#{$record->id}"),

            TextColumn::make('status')
                ->label(__('panel.cabinet.news_items.columns.status'))
                ->formatStateUsing(fn (string $state): string => __("panel.cabinet.news_items.status.{$state}")),

            TextColumn::make('publish_at')
                ->label(__('panel.cabinet.news_items.columns.publish_at'))
                ->dateTime(),
        ];
    }
}
