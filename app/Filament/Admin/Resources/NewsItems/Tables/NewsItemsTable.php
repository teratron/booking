<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\NewsItems\Tables;

use App\Models\NewsItem;
use App\Models\Object_;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class NewsItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(__('panel.news_items.form.title'))
                    ->getStateUsing(fn (NewsItem $record): string => $record->title ?? "#{$record->id}")
                    ->searchable(),

                TextColumn::make('object.name')
                    ->label(__('panel.news_items.form.object'))
                    ->getStateUsing(function (NewsItem $record): string {
                        // BelongsTo nullability inference is unreliable here
                        // (`object_id` is genuinely nullable) — an explicit
                        // instanceof check, not `?->`.
                        $object = $record->object;

                        return $object instanceof Object_ ? ($object->name ?? "#{$object->id}") : __('panel.news_items.form.any_object');
                    }),

                IconColumn::make('is_pinned')
                    ->label(__('panel.news_items.form.pinned'))
                    ->boolean(),

                TextColumn::make('status')
                    ->label(__('panel.news_items.form.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("panel.news_items.status.{$state}"))
                    ->sortable(),

                TextColumn::make('moderation_status')
                    ->label(__('panel.objects.columns.moderation_status'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state === null ? '—' : __("panel.news_items.moderation_status.{$state}")),

                TextColumn::make('view_count')
                    ->label(__('panel.news_items.columns.view_count'))
                    ->sortable(),

                TextColumn::make('publish_at')
                    ->label(__('panel.news_items.form.publish_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                TrashedFilter::make(),

                SelectFilter::make('status')
                    ->label(__('panel.news_items.form.status'))
                    ->options([
                        'draft' => __('panel.news_items.status.draft'),
                        'scheduled' => __('panel.news_items.status.scheduled'),
                        'published' => __('panel.news_items.status.published'),
                        'withdrawn' => __('panel.news_items.status.withdrawn'),
                    ]),
            ])
            ->recordActions([EditAction::make()])
            ->defaultSort('id', 'desc');
    }
}
