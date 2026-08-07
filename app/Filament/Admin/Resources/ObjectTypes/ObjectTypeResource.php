<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ObjectTypes;

use App\Filament\Admin\Resources\ObjectTypes\Pages\CreateObjectType;
use App\Filament\Admin\Resources\ObjectTypes\Pages\EditObjectType;
use App\Filament\Admin\Resources\ObjectTypes\Pages\ListObjectTypes;
use App\Filament\Admin\Resources\ObjectTypes\Schemas\ObjectTypeForm;
use App\Filament\Admin\Resources\ObjectTypes\Tables\ObjectTypesTable;
use App\Filament\Admin\Support\ScopedResource;
use App\Models\ObjectType;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The type registry. Declares no scope axis — it is portal-wide
 * configuration, and a category-scoped grant governs objects *of* a type,
 * not the type's own definition, so only an unrestricted grant reaches this
 * resource (the same posture `ModuleResource` takes for the same reason).
 */
class ObjectTypeResource extends ScopedResource
{
    protected static ?string $model = ObjectType::class;

    protected static string $permissionPrefix = 'settings';

    /** @var list<string> */
    protected static array $eagerLoad = ['translations', 'parent.translations', 'amenityGroups.translations'];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|UnitEnum|null $navigationGroup = 'catalog';

    public static function getNavigationLabel(): string
    {
        return __('panel.object_types.title');
    }

    public static function getModelLabel(): string
    {
        return __('panel.object_types.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.object_types.title');
    }

    public static function form(Schema $schema): Schema
    {
        return ObjectTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ObjectTypesTable::configure(self::applyTableDefaults($table));
    }

    /** @return array<string, mixed> */
    public static function getPages(): array
    {
        return [
            'index' => ListObjectTypes::route('/'),
            'create' => CreateObjectType::route('/create'),
            'edit' => EditObjectType::route('/{record}/edit'),
        ];
    }
}
