<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Objects;

use App\Filament\Admin\Resources\Objects\Pages\ListObjects;
use App\Filament\Admin\Resources\Objects\Tables\ObjectsTable;
use App\Filament\Admin\Support\ScopedResource;
use App\Models\Object_;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The portal's central record, listed for staff.
 *
 * Carries all three scope axes, so a country, region, or category
 * administrator's grants narrow this list before it runs — the resource
 * declares the columns and the contract does the narrowing.
 */
class ObjectResource extends ScopedResource
{
    protected static ?string $model = Object_::class;

    protected static string $permissionPrefix = 'object';

    protected static ?string $countryColumn = 'country_id';

    protected static ?string $territoryColumn = 'territory_id';

    protected static ?string $categoryColumn = 'object_type_id';

    /**
     * Every one of these is read by a column on every row, and strict mode
     * turns the lazy load that would otherwise follow into a thrown exception
     * rather than one extra query per row.
     *
     * @var list<string>
     */
    protected static array $eagerLoad = [
        'translations',
        'owner',
        'objectType.translations',
        'country.translations',
        'territory.translations',
    ];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|UnitEnum|null $navigationGroup = 'catalog';

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return __('panel.objects.title');
    }

    public static function getModelLabel(): string
    {
        return __('panel.objects.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.objects.title');
    }

    public static function table(Table $table): Table
    {
        return ObjectsTable::configure(self::applyTableDefaults($table));
    }

    /** @return array<string, mixed> */
    public static function getPages(): array
    {
        return [
            'index' => ListObjects::route('/'),
        ];
    }
}
