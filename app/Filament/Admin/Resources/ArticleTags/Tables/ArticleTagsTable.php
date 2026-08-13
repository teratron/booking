<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ArticleTags\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ArticleTagsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('panel.article_tags.form.name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('slug')
                    ->label(__('panel.article_tags.form.slug'))
                    ->searchable()
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label(__('panel.article_tags.columns.active'))
                    ->boolean()
                    ->sortable(),

                TextColumn::make('display_order')
                    ->label(__('panel.article_tags.form.display_order'))
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('panel.article_tags.columns.active')),
            ])
            ->recordActions([EditAction::make()])
            ->defaultSort('display_order');
    }
}
