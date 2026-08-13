<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PlacementTiers;

use App\Filament\Admin\Resources\PlacementTiers\Pages\EditPlacementTier;
use App\Filament\Admin\Resources\PlacementTiers\Pages\ListPlacementTiers;
use App\Filament\Admin\Resources\PlacementTiers\Schemas\PlacementTierForm;
use App\Filament\Admin\Resources\PlacementTiers\Tables\PlacementTiersTable;
use App\Filament\Admin\Support\ScopedResource;
use App\Models\PlacementTier;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The four structural ranks. There is no create or delete screen — rank
 * count and order are fixed by the schema, not administrator-created data —
 * only edit, for the presentation each rank carries (label, badges, colour,
 * active flag). Declares no scope axis: like the object type registry, this
 * is portal-wide configuration, so only an unrestricted grant reaches it.
 */
class PlacementTierResource extends ScopedResource
{
    protected static ?string $model = PlacementTier::class;

    protected static string $permissionPrefix = 'commerce';

    /** @var list<string> */
    protected static array $eagerLoad = ['translations'];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrophy;

    protected static string|UnitEnum|null $navigationGroup = 'commerce';

    public static function getNavigationLabel(): string
    {
        return __('panel.placement_tiers.title');
    }

    public static function getModelLabel(): string
    {
        return __('panel.placement_tiers.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.placement_tiers.title');
    }

    public static function form(Schema $schema): Schema
    {
        return PlacementTierForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PlacementTiersTable::configure(self::applyTableDefaults($table));
    }

    /** @return array<string, mixed> */
    public static function getPages(): array
    {
        return [
            'index' => ListPlacementTiers::route('/'),
            'edit' => EditPlacementTier::route('/{record}/edit'),
        ];
    }
}
