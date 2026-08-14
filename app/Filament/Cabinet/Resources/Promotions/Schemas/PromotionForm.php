<?php

declare(strict_types=1);

namespace App\Filament\Cabinet\Resources\Promotions\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

/**
 * The owner cabinet's promotion-authoring form — deliberately minimal:
 * exactly the five fields an owner needs to run a time-bounded offer on
 * their own object's page (title, description, image, start date, end
 * date), with no rich text editor and no layout control over how the
 * published promotion eventually renders. Every other `Promotion` column
 * (SEO fields, moderation state) is a staff-only concern, reachable through
 * the admin panel's own richer form instead.
 */
class PromotionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')
                ->label(__('panel.cabinet.promotions.form.title'))
                ->required()
                ->maxLength(255),

            Textarea::make('description')
                ->label(__('panel.cabinet.promotions.form.description'))
                ->required(),

            SpatieMediaLibraryFileUpload::make('image')
                ->label(__('panel.cabinet.promotions.form.image'))
                ->collection('image')
                ->image(),

            DatePicker::make('starts_at')
                ->label(__('panel.cabinet.promotions.form.starts_at'))
                ->default(now())
                ->required(),

            DatePicker::make('ends_at')
                ->label(__('panel.cabinet.promotions.form.ends_at'))
                ->required(),
        ]);
    }
}
