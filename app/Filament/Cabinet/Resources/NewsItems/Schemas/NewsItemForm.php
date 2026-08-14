<?php

declare(strict_types=1);

namespace App\Filament\Cabinet\Resources\NewsItems\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

/**
 * The owner cabinet's news-authoring form — deliberately minimal: exactly
 * the five fields an owner needs to announce something on their own
 * object's page (title, summary, body, image, publication date), with no
 * rich text editor and no layout control over how the published item
 * eventually renders. Every other `NewsItem` column (category, pinning,
 * gallery, SEO fields, portal-wide scope) is a staff-only concern, reachable
 * through the admin panel's own richer form instead.
 */
class NewsItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')
                ->label(__('panel.cabinet.news_items.form.title'))
                ->required()
                ->maxLength(255),

            Textarea::make('summary')
                ->label(__('panel.cabinet.news_items.form.summary')),

            Textarea::make('body')
                ->label(__('panel.cabinet.news_items.form.body'))
                ->required()
                ->rows(8),

            SpatieMediaLibraryFileUpload::make('image')
                ->label(__('panel.cabinet.news_items.form.image'))
                ->collection('cover_image')
                ->image(),

            DateTimePicker::make('publish_at')
                ->label(__('panel.cabinet.news_items.form.publish_at')),
        ]);
    }
}
