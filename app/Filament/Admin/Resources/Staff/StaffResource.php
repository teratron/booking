<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Staff;

use App\Filament\Admin\Resources\Staff\Pages\CreateStaff;
use App\Filament\Admin\Resources\Staff\Pages\EditStaff;
use App\Filament\Admin\Resources\Staff\Pages\ListStaff;
use App\Filament\Admin\Resources\Staff\RelationManagers\RoleGrantsRelationManager;
use App\Filament\Admin\Resources\Staff\Schemas\StaffForm;
use App\Filament\Admin\Resources\Staff\Tables\StaffTable;
use App\Filament\Admin\Support\ScopedResource;
use App\Models\User;
use App\Policies\StaffPolicy;
use App\Policies\UserPolicy;
use App\Services\Authorization\RoleGrantPresenter;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Override;
use UnitEnum;

/**
 * The staff accounts administered from the panel — every account holding a
 * portal-role grant, as opposed to `OwnerResource`'s object-owner accounts.
 *
 * The base query narrows by *excluding* the two object-side roles rather
 * than enumerating the nine panel roles: roles are data an administrator
 * may add to at any time, so a role seeded after this resource was written
 * must still surface here without a code change. `OwnerResource` scopes the
 * opposite way and is the shape this class mirrors, not a base to extend.
 */
class StaffResource extends ScopedResource
{
    /**
     * The two roles that describe a fact about an object's people rather
     * than about the portal's own staff — conferred through owner
     * management and the object form respectively, never through this
     * screen.
     */
    private const array OBJECT_SIDE_ROLES = ['object_owner', 'object_staff_member'];

    protected static ?string $model = User::class;

    protected static string $permissionPrefix = 'user';

    /**
     * The one relation the list's own "roles" column reads — loaded once
     * for the whole page here, not per row, so `RoleGrantPresenter` can
     * compose each line from the already-loaded models instead of issuing
     * its own query per staff account.
     *
     * @var list<string>
     */
    protected static array $eagerLoad = ['roleScopes.role'];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|UnitEnum|null $navigationGroup = 'access';

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return __('panel.staff.title');
    }

    public static function getModelLabel(): string
    {
        return __('panel.staff.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.staff.title');
    }

    /** @return Builder<User> */
    #[Override]
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<User> $query */
        $query = parent::getEloquentQuery();

        return $query->whereDoesntHave(
            'roles',
            fn (Builder $roles): Builder => $roles->whereIn('name', self::OBJECT_SIDE_ROLES),
        );
    }

    public static function table(Table $table): Table
    {
        return StaffTable::configure(self::applyTableDefaults($table));
    }

    public static function form(Schema $schema): Schema
    {
        return StaffForm::configure($schema);
    }

    /** @return array<class-string> */
    public static function getRelations(): array
    {
        return [
            RoleGrantsRelationManager::class,
        ];
    }

    /** @return array<string, mixed> */
    public static function getPages(): array
    {
        return [
            'index' => ListStaff::route('/'),
            'create' => CreateStaff::route('/create'),
            'edit' => EditStaff::route('/{record}/edit'),
        ];
    }

    /**
     * Deliberately not the model-bound {@see UserPolicy}:
     * `OwnerResource` shares the same `User` model and Laravel resolves
     * exactly one policy per class, so this resource authorizes directly
     * against {@see StaffPolicy} instead of through the automatic
     * model-policy lookup `HasAuthorization` otherwise performs. Every
     * action collapses to the same chief-administrator check for now —
     * see {@see StaffPolicy}'s own docblock for why the two screens cannot
     * share a policy and what is deliberately left open.
     */
    #[Override]
    public static function getAuthorizationResponse(string|UnitEnum $action, ?Model $record = null): Response
    {
        $user = Filament::auth()->user();

        if (! $user instanceof User) {
            return Response::deny();
        }

        $policy = app(StaffPolicy::class);

        // Mirrors Filament's own `get_authorization_response()` helper,
        // which this override replaces entirely: a BackedEnum action
        // resolves through its ->value, a plain UnitEnum through its
        // ->name, since neither implements Stringable for a bare cast.
        $actionName = match (true) {
            $action instanceof BackedEnum => $action->value,
            $action instanceof UnitEnum => $action->name,
            default => $action,
        };

        $allowed = match ($actionName) {
            'viewAny' => $policy->viewAny($user),
            'create' => $policy->create($user),
            'view' => $record instanceof User && $policy->view($user, $record),
            'update' => $record instanceof User && $policy->update($user, $record),
            'delete', 'deleteAny', 'forceDelete', 'forceDeleteAny' => $record instanceof User
                ? $policy->delete($user, $record)
                : $policy->viewAny($user),
            default => $policy->viewAny($user),
        };

        return $allowed ? Response::allow() : Response::deny();
    }

    /**
     * The role select every grant action on this resource offers options
     * from — the launch role set plus anything an administrator has since
     * added, excluding the two object-side roles this screen never grants.
     *
     * @return array<int, string>
     */
    public static function grantableRoleOptions(): array
    {
        return app(RoleGrantPresenter::class)->roleOptions(self::OBJECT_SIDE_ROLES);
    }
}
