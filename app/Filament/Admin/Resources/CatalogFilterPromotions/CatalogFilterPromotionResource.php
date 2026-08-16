<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\CatalogFilterPromotions;

use App\Filament\Admin\Resources\CatalogFilterPromotions\Pages\CreateCatalogFilterPromotion;
use App\Filament\Admin\Resources\CatalogFilterPromotions\Pages\EditCatalogFilterPromotion;
use App\Filament\Admin\Resources\CatalogFilterPromotions\Pages\ListCatalogFilterPromotions;
use App\Filament\Admin\Resources\CatalogFilterPromotions\Schemas\CatalogFilterPromotionForm;
use App\Filament\Admin\Resources\CatalogFilterPromotions\Tables\CatalogFilterPromotionsTable;
use App\Filament\Admin\Resources\Redirects\RedirectResource;
use App\Filament\Admin\Support\ScopedResource;
use App\Models\CatalogFilterPromotion;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The catalog indexation allowlist's own administration screen — portal-wide
 * configuration, not scoped to a country, territory, or category, the same
 * posture {@see RedirectResource}
 * takes for the redirect table.
 */
class CatalogFilterPromotionResource extends ScopedResource
{
    protected static ?string $model = CatalogFilterPromotion::class;

    protected static string $permissionPrefix = 'seo';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFunnel;

    protected static string|UnitEnum|null $navigationGroup = 'seo';

    public static function getNavigationLabel(): string
    {
        return __('panel.catalog_filter_promotions.title');
    }

    public static function getModelLabel(): string
    {
        return __('panel.catalog_filter_promotions.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.catalog_filter_promotions.title');
    }

    public static function form(Schema $schema): Schema
    {
        return CatalogFilterPromotionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CatalogFilterPromotionsTable::configure(self::applyTableDefaults($table));
    }

    /** @return array<string, mixed> */
    public static function getPages(): array
    {
        return [
            'index' => ListCatalogFilterPromotions::route('/'),
            'create' => CreateCatalogFilterPromotion::route('/create'),
            'edit' => EditCatalogFilterPromotion::route('/{record}/edit'),
        ];
    }
}
