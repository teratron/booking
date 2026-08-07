<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Owners;

use App\Filament\Admin\Resources\Owners\Pages\CreateOwner;
use App\Filament\Admin\Resources\Owners\Pages\EditOwner;
use App\Filament\Admin\Resources\Owners\Pages\ListOwners;
use App\Filament\Admin\Resources\Owners\RelationManagers\ObjectsRelationManager;
use App\Filament\Admin\Resources\Owners\Schemas\OwnerForm;
use App\Filament\Admin\Resources\Owners\Tables\OwnersTable;
use App\Filament\Admin\Support\ScopedResource;
use App\Models\User;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Override;
use UnitEnum;

/**
 * The object-owner accounts staff manages on an owner's behalf — creating
 * one, editing contact details, attaching or detaching the objects it holds,
 * and suspending or restoring its panel access.
 *
 * Owners carry only the country axis: unlike an object, an account has no
 * territory or category of its own, so this resource declares no columns for
 * either. The base query additionally narrows to accounts holding the
 * `object_owner` role — every other staff account, including the actor's
 * own, must never surface on this screen, which is a list of owners, not of
 * every user row the table happens to hold.
 */
class OwnerResource extends ScopedResource
{
    protected static ?string $model = User::class;

    protected static string $permissionPrefix = 'user';

    protected static ?string $countryColumn = 'country_id';

    /**
     * The only relation a column on the list reads through — country.code
     * for the country column. Object count and last sign-in are pulled in
     * as selected columns on the query itself, not relations, so they carry
     * no entry here.
     *
     * @var list<string>
     */
    protected static array $eagerLoad = ['country'];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'access';

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return __('panel.owners.title');
    }

    public static function getModelLabel(): string
    {
        return __('panel.owners.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.owners.title');
    }

    /** @return Builder<User> */
    #[Override]
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<User> $query */
        $query = parent::getEloquentQuery();

        return $query->role('object_owner');
    }

    public static function table(Table $table): Table
    {
        return OwnersTable::configure(self::applyTableDefaults($table));
    }

    public static function form(Schema $schema): Schema
    {
        return OwnerForm::configure($schema);
    }

    /** @return array<class-string> */
    public static function getRelations(): array
    {
        return [
            ObjectsRelationManager::class,
        ];
    }

    /** @return array<string, mixed> */
    public static function getPages(): array
    {
        return [
            'index' => ListOwners::route('/'),
            'create' => CreateOwner::route('/create'),
            'edit' => EditOwner::route('/{record}/edit'),
        ];
    }
}
