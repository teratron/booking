<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Articles\Tables;

use App\Models\Article;
use App\Models\ArticleCategory;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ArticlesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(__('panel.articles.form.title'))
                    ->getStateUsing(fn (Article $record): string => $record->title ?? "#{$record->id}")
                    ->searchable(),

                TextColumn::make('author.name')
                    ->label(__('panel.articles.form.author'))
                    ->sortable(),

                TextColumn::make('category.name')
                    ->label(__('panel.articles.form.category'))
                    ->getStateUsing(function (Article $record): string {
                        // BelongsTo nullability inference is unreliable here
                        // (`article_category_id` is genuinely nullable) — an
                        // explicit instanceof check, not `?->`, is the
                        // resolution this codebase uses case-by-case for it.
                        $category = $record->category;

                        return $category instanceof ArticleCategory ? ($category->name ?? $category->slug) : '—';
                    }),

                TextColumn::make('status')
                    ->label(__('panel.articles.form.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("panel.articles.status.{$state}"))
                    ->sortable(),

                TextColumn::make('publish_at')
                    ->label(__('panel.articles.form.publish_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                TrashedFilter::make(),

                SelectFilter::make('status')
                    ->label(__('panel.articles.form.status'))
                    ->options([
                        'draft' => __('panel.articles.status.draft'),
                        'scheduled' => __('panel.articles.status.scheduled'),
                        'published' => __('panel.articles.status.published'),
                    ]),

                SelectFilter::make('article_category_id')
                    ->label(__('panel.articles.form.category'))
                    ->options(fn (): array => ArticleCategory::query()
                        ->with('translations')
                        ->get()
                        ->mapWithKeys(fn (ArticleCategory $category): array => [$category->id => $category->name ?? $category->slug])
                        ->all()),
            ])
            ->recordActions([EditAction::make()])
            ->defaultSort('id', 'desc');
    }
}
