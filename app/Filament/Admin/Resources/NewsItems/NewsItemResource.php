<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\NewsItems;

use App\Filament\Admin\Resources\NewsItems\Pages\CreateNewsItem;
use App\Filament\Admin\Resources\NewsItems\Pages\EditNewsItem;
use App\Filament\Admin\Resources\NewsItems\Pages\ListNewsItems;
use App\Filament\Admin\Resources\NewsItems\Schemas\NewsItemForm;
use App\Filament\Admin\Resources\NewsItems\Tables\NewsItemsTable;
use App\Filament\Admin\Support\ScopedResource;
use App\Models\NewsItem;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Owner- or administrator-authored news, object-scoped or portal-wide.
 * Declares only a territory scope axis — `news_items` carries no
 * `country_id`/`object_type_id` column of its own to narrow against.
 */
class NewsItemResource extends ScopedResource
{
    protected static ?string $model = NewsItem::class;

    protected static string $permissionPrefix = 'content';

    protected static ?string $territoryColumn = 'territory_id';

    /** @var list<string> */
    protected static array $eagerLoad = ['translations', 'author', 'object.translations', 'territory.translations', 'category.translations'];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static string|UnitEnum|null $navigationGroup = 'content';

    public static function getNavigationLabel(): string
    {
        return __('panel.news_items.title');
    }

    public static function getModelLabel(): string
    {
        return __('panel.news_items.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.news_items.title');
    }

    public static function form(Schema $schema): Schema
    {
        return NewsItemForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return NewsItemsTable::configure(self::applyTableDefaults($table));
    }

    /** @return array<string, mixed> */
    public static function getPages(): array
    {
        return [
            'index' => ListNewsItems::route('/'),
            'create' => CreateNewsItem::route('/create'),
            'edit' => EditNewsItem::route('/{record}/edit'),
        ];
    }
}
