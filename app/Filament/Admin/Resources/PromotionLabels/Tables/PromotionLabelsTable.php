<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PromotionLabels\Tables;

use App\Models\PromotionLabel;
use App\Support\Advertising\CardPosition;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PromotionLabelsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('text')
                    ->label(__('panel.promotion_labels.form.text'))
                    ->getStateUsing(fn (PromotionLabel $record): string => $record->text ?? "#{$record->id}"),

                ColorColumn::make('border_colour')
                    ->label(__('panel.promotion_labels.form.border_colour')),

                ColorColumn::make('text_colour')
                    ->label(__('panel.promotion_labels.form.text_colour')),

                ColorColumn::make('background_colour')
                    ->label(__('panel.promotion_labels.form.background_colour')),

                TextColumn::make('icon')
                    ->label(__('panel.promotion_labels.form.icon'))
                    ->placeholder('—'),

                TextColumn::make('position_on_card')
                    ->label(__('panel.promotion_labels.form.position_on_card'))
                    ->badge()
                    ->getStateUsing(fn (PromotionLabel $record): string => $record->position_on_card->value)
                    ->formatStateUsing(fn (string $state): string => __("panel.promotion_labels.positions.{$state}")),

                IconColumn::make('is_active')
                    ->label(__('panel.promotion_labels.columns.active'))
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('position_on_card')
                    ->label(__('panel.promotion_labels.form.position_on_card'))
                    ->options(fn (): array => collect(CardPosition::cases())
                        ->mapWithKeys(fn (CardPosition $position): array => [
                            $position->value => __("panel.promotion_labels.positions.{$position->value}"),
                        ])
                        ->all()),

                TernaryFilter::make('is_active')
                    ->label(__('panel.promotion_labels.columns.active')),
            ])
            ->recordActions([EditAction::make()])
            ->defaultSort('id');
    }
}
