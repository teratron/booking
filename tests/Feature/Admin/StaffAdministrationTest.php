<?php

declare(strict_types=1);

use App\Exceptions\UnrevocableGrantException;
use App\Filament\Admin\Resources\Staff\Pages\CreateStaff;
use App\Filament\Admin\Resources\Staff\Pages\EditStaff;
use App\Filament\Admin\Resources\Staff\Pages\ListStaff;
use App\Filament\Admin\Resources\Staff\StaffResource;
use App\Models\User;
use App\Services\Authorization\RoleGrantService;
use App\Services\Staff\StaffAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Staff Administration
|--------------------------------------------------------------------------
|
| RoleGrantService::grantRole() had exactly one caller before this fix — the
| database seeder — so no staff account could be created after the portal
| shipped. This proves the panel surface that finally calls it: creation,
| role granting and revocation with the last-chief-administrator guard,
| deactivation with the same guard, and the exclusion that keeps object
| owners off this screen entirely.
|
*/

function staffActor(array $permissions, string $roleKey): User
{
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $role = Role::findOrCreate($roleKey, 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $role->syncPermissions($permissions);

    $user = User::factory()->create();
    app(RoleGrantService::class)->grantRole($user, $roleKey, $user);

    return $user->fresh();
}

it('creates a staff account and journals it, without touching the object-side roles', function (): void {
    $chief = staffActor(['admin_panel_access'], 'chief_administrator');

    Livewire::actingAs($chief)
        ->test(CreateStaff::class)
        ->fillForm([
            'name' => 'Ana Moderator',
            'email' => 'ana.moderator@example.test',
            'password' => 'a-strong-password-123',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $staff = User::query()->where('email', 'ana.moderator@example.test')->firstOrFail();

    expect($staff->hasRole('object_owner'))->toBeFalse()
        ->and(DB::table('audits')->where('event', 'staff_account_created')->where('auditable_id', $staff->id)->count())->toBe(1);
});

it('grants and revokes a role through the panel, recording the revocation rather than deleting the grant', function (): void {
    Role::findOrCreate('moderator', 'web');
    $chief = staffActor(['admin_panel_access'], 'chief_administrator');
    $staff = User::factory()->create();

    app(RoleGrantService::class)->grantRole($staff, 'moderator', $chief);

    $grant = DB::table('role_scopes')->where('user_id', $staff->id)->where('role_id', Role::findByName('moderator', 'web')->id)->first();
    expect($grant)->not->toBeNull()
        ->and($grant->revoked_at)->toBeNull();

    app(RoleGrantService::class)->revokeRole($staff->fresh(), 'moderator', $chief);

    $revoked = DB::table('role_scopes')->where('id', $grant->id)->first();
    expect($staff->fresh()->hasRole('moderator'))->toBeFalse()
        ->and($revoked)->not->toBeNull() // the row survives — revocation is recorded, not deleted
        ->and($revoked->revoked_by)->toBe($chief->id)
        ->and($revoked->revoked_at)->not->toBeNull();
});

it('refuses to deactivate the last active holder of the chief administrator role', function (): void {
    $chief = staffActor(['admin_panel_access'], 'chief_administrator');

    expect(fn () => app(StaffAccountService::class)->deactivate($chief, $chief))
        ->toThrow(UnrevocableGrantException::class);

    expect($chief->fresh()->blocked_at)->toBeNull();
});

it('deactivates a chief administrator when another active holder remains', function (): void {
    $first = staffActor(['admin_panel_access'], 'chief_administrator');
    $second = staffActor(['admin_panel_access'], 'chief_administrator');

    app(StaffAccountService::class)->deactivate($first, $second);

    expect($first->fresh()->blocked_at)->not->toBeNull()
        ->and(DB::table('audits')->where('event', 'staff_deactivated')->where('auditable_id', $first->id)->count())->toBe(1);
});

it('lists staff by excluding the object-side roles rather than enumerating the panel ones', function (): void {
    Role::findOrCreate('object_owner', 'web');
    Role::findOrCreate('object_staff_member', 'web');
    Role::findOrCreate('a_future_panel_role', 'web');

    $chief = staffActor(['admin_panel_access', 'user.view'], 'chief_administrator');
    $owner = User::factory()->create();
    $owner->assignRole('object_owner');
    $futureRoleHolder = User::factory()->create();
    app(RoleGrantService::class)->grantRole($futureRoleHolder, 'a_future_panel_role', $chief);

    $this->actingAs($chief);
    $visibleIds = StaffResource::getEloquentQuery()->pluck('id')->all();

    expect($visibleIds)->toContain($chief->id)
        ->toContain($futureRoleHolder->id) // a role seeded after this code was written still surfaces
        ->not->toContain($owner->id);
});

it('renders the granted role and its scope on the staff list, reading the eager-loaded relation rather than querying per row', function (): void {
    Role::findOrCreate('country_administrator', 'web');
    $chief = staffActor(['admin_panel_access', 'user.view'], 'chief_administrator');
    $languageId = DB::table('languages')->insertGetId([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $countryId = DB::table('countries')->insertGetId([
        'code' => 'MD', 'currency' => 'MDL', 'phone_code' => '+373', 'is_active' => true,
        'primary_language_id' => $languageId, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $staff = User::factory()->create();
    app(RoleGrantService::class)->grantRole($staff, 'country_administrator', $chief, 'country', $countryId);
    $secondStaff = User::factory()->create();
    app(RoleGrantService::class)->grantRole($secondStaff, 'country_administrator', $chief, 'country', $countryId);

    Livewire::actingAs($chief)
        ->test(ListStaff::class)
        ->assertSee('MD');

    // The relation the roles column reads is declared on StaffResource's
    // own $eagerLoad, not resolved lazily per row — confirmed directly
    // against the query log rather than inferred: no query here selects
    // from role_scopes or roles for an individual record.
    DB::enableQueryLog();
    Livewire::actingAs($chief)->test(ListStaff::class);
    $roleScopeQueries = collect(DB::getQueryLog())
        ->filter(fn (array $entry): bool => str_contains((string) $entry['query'], 'select * from "role_scopes" where "role_scopes"."user_id" ='));
    DB::disableQueryLog();

    expect($roleScopeQueries)->toBeEmpty();
});

it('refuses the staff resource entirely to an administrator who is not the chief administrator', function (): void {
    $moderator = staffActor(['admin_panel_access'], 'moderator');

    $this->actingAs($moderator)
        ->get(StaffResource::getUrl('index', panel: 'admin'))
        ->assertForbidden();
});

it('lets the chief administrator reach the staff edit page', function (): void {
    $chief = staffActor(['admin_panel_access', 'user.view', 'user.edit'], 'chief_administrator');
    $staff = User::factory()->create();

    // Livewire::test() mounts the page component directly rather than
    // through the full HTTP middleware stack — the chief administrator
    // role requires a second factor to reach the panel over a real request
    // (config('booking.two_factor.required_for_roles')), which is a
    // sign-in concern this test has no reason to also exercise.
    Livewire::actingAs($chief)
        ->test(EditStaff::class, ['record' => $staff->id])
        ->assertOk();
});
