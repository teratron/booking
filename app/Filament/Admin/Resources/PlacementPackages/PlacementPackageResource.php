<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PlacementPackages;

use App\Filament\Admin\Resources\PlacementPackages\Pages\CreatePlacementPackage;
use App\Filament\Admin\Resources\PlacementPackages\Pages\EditPlacementPackage;
use App\Filament\Admin\Resources\PlacementPackages\Pages\ListPlacementPackages;
use App\Filament\Admin\Resources\PlacementPackages\Schemas\PlacementPackageForm;
use App\Filament\Admin\Resources\PlacementPackages\Tables\PlacementPackagesTable;
use App\Filament\Admin\Support\ScopedResource;
use App\Models\PlacementPackage;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Administrator-defined, purchasable grants of a tier for a period. Package
 * sets may differ per object category, but the resource itself declares no
 * scope axis — like the object type registry it configures against, package
 * *definitions* are portal-wide; only which package an object currently
 * holds is per-object.
 */
class PlacementPackageResource extends ScopedResource
{
    protected static ?string $model = PlacementPackage::class;

    protected static string $permissionPrefix = 'commerce';

    /** @var list<string> */
    protected static array $eagerLoad = ['translations', 'tier.translations', 'objectType.translations'];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static string|UnitEnum|null $navigationGroup = 'commerce';

    public static function getNavigationLabel(): string
    {
        return __('panel.placement_packages.title');
    }

    public static function getModelLabel(): string
    {
        return __('panel.placement_packages.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.placement_packages.title');
    }

    public static function form(Schema $schema): Schema
    {
        return PlacementPackageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PlacementPackagesTable::configure(self::applyTableDefaults($table));
    }

    /** @return array<string, mixed> */
    public static function getPages(): array
    {
        return [
            'index' => ListPlacementPackages::route('/'),
            'create' => CreatePlacementPackage::route('/create'),
            'edit' => EditPlacementPackage::route('/{record}/edit'),
        ];
    }
}
