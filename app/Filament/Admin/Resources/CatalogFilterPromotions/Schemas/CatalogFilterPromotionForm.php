<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\CatalogFilterPromotions\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CatalogFilterPromotionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('signature')
                ->label(__('panel.catalog_filter_promotions.form.signature'))
                ->helperText(__('panel.catalog_filter_promotions.form.signature_hint'))
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(255),

            Toggle::make('is_active')
                ->label(__('panel.catalog_filter_promotions.form.active'))
                ->default(true),
        ]);
    }
}
