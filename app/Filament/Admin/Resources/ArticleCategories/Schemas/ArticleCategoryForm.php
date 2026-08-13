<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ArticleCategories\Schemas;

use App\Models\Language;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ArticleCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('slug')
                ->label(__('panel.article_categories.form.slug'))
                ->required()
                ->unique(ignoreRecord: true)
                ->alphaDash()
                ->maxLength(255),

            Toggle::make('is_active')->label(__('panel.article_categories.columns.active'))->default(true),
            TextInput::make('display_order')->label(__('panel.article_categories.form.display_order'))->numeric()->default(0),

            ...self::perLanguageSections(),
        ]);
    }

    /** @return list<Section> */
    private static function perLanguageSections(): array
    {
        return array_values(
            Language::query()
                ->where('is_active', true)
                ->orderBy('display_order')
                ->get()
                ->map(fn (Language $language): Section => Section::make($language->short_label)
                    ->collapsible()
                    ->schema([
                        TextInput::make("translations.{$language->code}.name")
                            ->label(__('panel.article_categories.form.name'))
                            ->maxLength(255),
                    ]))
                ->all(),
        );
    }
}
