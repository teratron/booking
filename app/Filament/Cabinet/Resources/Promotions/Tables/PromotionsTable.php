<?php

declare(strict_types=1);

namespace App\Filament\Cabinet\Resources\Promotions\Tables;

use App\Filament\Cabinet\Resources\Promotions\PromotionResource;
use App\Models\Promotion;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The owner's own promotion list for the currently selected object —
 * including whatever is still pending, rejected, or sent back for revision,
 * since {@see PromotionResource::getEloquentQuery()}
 * strips the moderation scope that would otherwise hide those from the
 * owner's own cabinet.
 */
class PromotionsTable
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
                ->label(__('panel.cabinet.promotions.columns.title'))
                ->getStateUsing(fn (Promotion $record): string => $record->title ?? "#{$record->id}"),

            TextColumn::make('status')
                ->label(__('panel.cabinet.promotions.columns.status'))
                ->formatStateUsing(fn (string $state): string => __("panel.cabinet.promotions.status.{$state}")),

            TextColumn::make('starts_at')
                ->label(__('panel.cabinet.promotions.columns.starts_at'))
                ->date(),

            TextColumn::make('ends_at')
                ->label(__('panel.cabinet.promotions.columns.ends_at'))
                ->date(),
        ];
    }
}
