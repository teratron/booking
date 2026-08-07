<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Territories;

use App\Filament\Admin\Resources\Territories\Pages\CreateTerritory;
use App\Filament\Admin\Resources\Territories\Pages\EditTerritory;
use App\Filament\Admin\Resources\Territories\Pages\ListTerritories;
use App\Filament\Admin\Resources\Territories\Schemas\TerritoryForm;
use App\Filament\Admin\Resources\Territories\Tables\TerritoriesTable;
use App\Filament\Admin\Support\ScopedResource;
use App\Models\Territory;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The territory hierarchy, scoped on country and on each node's own identity
 * — a region-scoped grant reaches every descendant through the same subtree
 * expansion the object list already relies on.
 */
class TerritoryResource extends ScopedResource
{
    protected static ?string $model = Territory::class;

    protected static string $permissionPrefix = 'geography';

    protected static ?string $countryColumn = 'country_id';

    protected static ?string $territoryColumn = 'id';

    /** @var list<string> */
    protected static array $eagerLoad = ['translations', 'level.translations', 'country.translations', 'parent.translations'];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static string|UnitEnum|null $navigationGroup = 'geography';

    public static function getNavigationLabel(): string
    {
        return __('panel.territories.title');
    }

    public static function getModelLabel(): string
    {
        return __('panel.territories.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.territories.title');
    }

    public static function form(Schema $schema): Schema
    {
        return TerritoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TerritoriesTable::configure(self::applyTableDefaults($table));
    }

    /** @return array<string, mixed> */
    public static function getPages(): array
    {
        return [
            'index' => ListTerritories::route('/'),
            'create' => CreateTerritory::route('/create'),
            'edit' => EditTerritory::route('/{record}/edit'),
        ];
    }
}
