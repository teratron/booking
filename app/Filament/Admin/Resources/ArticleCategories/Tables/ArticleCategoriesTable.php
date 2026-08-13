<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ArticleCategories\Tables;

use App\Models\ArticleCategory;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ArticleCategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('slug')
                    ->label(__('panel.article_categories.form.slug'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label(__('panel.article_categories.form.name'))
                    ->getStateUsing(fn (ArticleCategory $record): string => $record->name ?? $record->slug),

                IconColumn::make('is_active')
                    ->label(__('panel.article_categories.columns.active'))
                    ->boolean()
                    ->sortable(),

                TextColumn::make('display_order')
                    ->label(__('panel.article_categories.form.display_order'))
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('panel.article_categories.columns.active')),
            ])
            ->recordActions([EditAction::make()])
            ->defaultSort('display_order');
    }
}
