<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ArticleTags\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ArticleTagForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('slug')
                ->label(__('panel.article_tags.form.slug'))
                ->required()
                ->unique(ignoreRecord: true)
                ->alphaDash()
                ->maxLength(255),

            TextInput::make('name')
                ->label(__('panel.article_tags.form.name'))
                ->required()
                ->maxLength(255),

            Toggle::make('is_active')->label(__('panel.article_tags.columns.active'))->default(true),
            TextInput::make('display_order')->label(__('panel.article_tags.form.display_order'))->numeric()->default(0),
        ]);
    }
}
