<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Promotions;

use App\Filament\Admin\Resources\Promotions\Pages\CreatePromotion;
use App\Filament\Admin\Resources\Promotions\Pages\EditPromotion;
use App\Filament\Admin\Resources\Promotions\Pages\ListPromotions;
use App\Filament\Admin\Resources\Promotions\Schemas\PromotionForm;
use App\Filament\Admin\Resources\Promotions\Tables\PromotionsTable;
use App\Filament\Admin\Support\ScopedResource;
use App\Models\Promotion;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Owner- or administrator-authored, time-bounded promotional content —
 * NOT the card-decoration `PromotionLabel`/`ObjectPromotion` pair, a
 * separate, unrelated feature. Declares only a territory scope axis, like
 * `NewsItemResource` — `promotions` carries no `country_id`/
 * `object_type_id` column of its own.
 */
class PromotionResource extends ScopedResource
{
    protected static ?string $model = Promotion::class;

    protected static string $permissionPrefix = 'content';

    protected static ?string $territoryColumn = 'territory_id';

    /** @var list<string> */
    protected static array $eagerLoad = ['translations', 'object.translations', 'territory.translations'];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGift;

    protected static string|UnitEnum|null $navigationGroup = 'content';

    public static function getNavigationLabel(): string
    {
        return __('panel.promotions.title');
    }

    public static function getModelLabel(): string
    {
        return __('panel.promotions.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.promotions.title');
    }

    public static function form(Schema $schema): Schema
    {
        return PromotionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PromotionsTable::configure(self::applyTableDefaults($table));
    }

    /** @return array<string, mixed> */
    public static function getPages(): array
    {
        return [
            'index' => ListPromotions::route('/'),
            'create' => CreatePromotion::route('/create'),
            'edit' => EditPromotion::route('/{record}/edit'),
        ];
    }
}
