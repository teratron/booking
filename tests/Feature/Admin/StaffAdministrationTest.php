<?php

declare(strict_types=1);

use App\Exceptions\UnrevocableGrantException;
use App\Filament\Admin\Resources\Staff\Pages\CreateStaff;
use App\Filament\Admin\Resources\Staff\Pages\EditStaff;
use App\Filament\Admin\Resources\Staff\Pages\ListStaff;
use App\Filament\Admin\Resources\Staff\RelationManagers\RoleGrantsRelationManager;
use App\Filament\Admin\Resources\Staff\StaffResource;
use App\Models\RoleScope;
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

/*
|--------------------------------------------------------------------------
| Edit Page — Status Toggle And Two-Factor Reset
|--------------------------------------------------------------------------
|
| handleRecordUpdate() translates the form's is_active toggle into a
| StaffAccountService::deactivate()/restore() call, and the page's own
| header action resets a staff account's two-factor enrolment. Neither was
| exercised by the mount-only "reaches the edit page" test above.
|
*/

it('restores a deactivated staff account through the edit form\'s status toggle', function (): void {
    $chief = staffActor(['admin_panel_access', 'user.view', 'user.edit'], 'chief_administrator');
    $staff = User::factory()->create(['blocked_at' => now()->subDay(), 'blocked_by' => $chief->id]);

    Livewire::actingAs($chief)
        ->test(EditStaff::class, ['record' => $staff->id])
        ->fillForm(['name' => $staff->name, 'email' => $staff->email, 'is_active' => true])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($staff->fresh()->blocked_at)->toBeNull()
        ->and(DB::table('audits')->where('event', 'staff_restored')->where('auditable_id', $staff->id)->count())->toBe(1);
});

it('deactivates an active staff account through the edit form\'s status toggle', function (): void {
    $chief = staffActor(['admin_panel_access', 'user.view', 'user.edit'], 'chief_administrator');
    $staff = User::factory()->create();

    Livewire::actingAs($chief)
        ->test(EditStaff::class, ['record' => $staff->id])
        ->fillForm(['name' => $staff->name, 'email' => $staff->email, 'is_active' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($staff->fresh()->blocked_at)->not->toBeNull()
        ->and(DB::table('audits')->where('event', 'staff_deactivated')->where('auditable_id', $staff->id)->count())->toBe(1);
});

it('rolls back the whole save, contact edits included, when the toggle would deactivate the last active chief administrator', function (): void {
    $chief = staffActor(['admin_panel_access', 'user.view', 'user.edit'], 'chief_administrator');
    $originalName = $chief->name;

    Livewire::actingAs($chief)
        ->test(EditStaff::class, ['record' => $chief->id])
        ->fillForm(['name' => 'Renamed While Refused', 'email' => $chief->email, 'is_active' => false])
        ->call('save')
        ->assertNotified(__('panel.staff.actions.deactivation_refused'));

    $fresh = $chief->fresh();

    // Bug fixed here: handleRecordUpdate() saves the contact change via
    // StaffAccountService::updateContacts() before the deactivation guard
    // ever runs, inside the transaction Filament's EditRecord::save() opens
    // around the whole update. A bare `throw new Halt` defaults to
    // *committing* that transaction, so the rename would have survived a
    // "refused" save — the exact partial success this class's own docblock
    // says can never happen. Fixed by rolling the transaction back.
    expect($fresh->blocked_at)->toBeNull()
        ->and($fresh->name)->toBe($originalName)
        ->and(DB::table('audits')->where('event', 'staff_contacts_updated')->where('auditable_id', $chief->id)->count())->toBe(0);
});

it('clears a staff account\'s two-factor secret through the reset action, forcing re-enrolment on its next sign-in', function (): void {
    $chief = staffActor(['admin_panel_access', 'user.view', 'user.edit'], 'chief_administrator');
    $staff = User::factory()->create();
    $staff->saveAppAuthenticationSecret('a-fake-secret');

    Livewire::actingAs($chief)
        ->test(EditStaff::class, ['record' => $staff->id])
        ->assertActionVisible('reset_two_factor')
        ->callAction('reset_two_factor')
        ->assertNotified(__('panel.staff.actions.applied'));

    expect($staff->fresh()->getAppAuthenticationSecret())->toBeNull();
});

it('hides the two-factor reset action for a staff account that never enrolled', function (): void {
    $chief = staffActor(['admin_panel_access', 'user.view', 'user.edit'], 'chief_administrator');
    $staff = User::factory()->create();

    Livewire::actingAs($chief)
        ->test(EditStaff::class, ['record' => $staff->id])
        ->assertActionHidden('reset_two_factor');
});

/*
|--------------------------------------------------------------------------
| Role Grants Relation Manager
|--------------------------------------------------------------------------
|
| The staff edit page's "Roles & grants" tab — previously never rendered by
| any test. Proves it lists a staff account's grants (active and revoked
| alike) with their scope and granter, and that its own grant/revoke header
| and record actions actually call RoleGrantService and reflect the result,
| the same way OwnerObjectsRelationManagerTest proves for the owner side.
|
*/

it('lists a staff account\'s active and revoked role grants with their scope, granter, and status', function (): void {
    Role::findOrCreate('moderator', 'web');
    Role::findOrCreate('country_administrator', 'web');
    $chief = staffActor(['admin_panel_access', 'user.view', 'user.edit'], 'chief_administrator');
    $staff = User::factory()->create();

    $languageId = DB::table('languages')->insertGetId([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $countryId = DB::table('countries')->insertGetId([
        'code' => 'MD', 'currency' => 'MDL', 'phone_code' => '+373', 'is_active' => true,
        'primary_language_id' => $languageId, 'created_at' => now(), 'updated_at' => now(),
    ]);

    app(RoleGrantService::class)->grantRole($staff, 'moderator', $chief);
    app(RoleGrantService::class)->grantRole($staff, 'country_administrator', $chief, 'country', $countryId);
    app(RoleGrantService::class)->revokeRole($staff->fresh(), 'country_administrator', $chief);

    $activeGrant = RoleScope::query()->where('user_id', $staff->id)->whereNull('revoked_at')->firstOrFail();
    $revokedGrant = RoleScope::query()->where('user_id', $staff->id)->whereNotNull('revoked_at')->firstOrFail();

    $component = Livewire::actingAs($chief)->test(RoleGrantsRelationManager::class, [
        'ownerRecord' => $staff->fresh(),
        'pageClass' => EditStaff::class,
    ]);

    $component->assertCanSeeTableRecords([$activeGrant, $revokedGrant]);

    $component->assertSee('moderator')
        ->assertSee('MD')
        ->assertSee($chief->name)
        ->assertSee(__('panel.staff.grants.status.active'))
        ->assertSee(__('panel.staff.grants.status.revoked'));

    $component->assertTableActionVisible('revoke', $activeGrant)
        ->assertTableActionHidden('revoke', $revokedGrant);
});

it('grants a new unrestricted role to a staff account through the relation manager\'s grant action', function (): void {
    Role::findOrCreate('moderator', 'web');
    $chief = staffActor(['admin_panel_access', 'user.view', 'user.edit'], 'chief_administrator');
    $staff = User::factory()->create();
    $moderatorRoleId = Role::findByName('moderator', 'web')->id;

    Livewire::actingAs($chief)
        ->test(RoleGrantsRelationManager::class, ['ownerRecord' => $staff, 'pageClass' => EditStaff::class])
        ->callTableAction('grant', data: ['role_id' => $moderatorRoleId, 'scope_kind' => 'none'])
        ->assertNotified(__('panel.staff.grants.applied'));

    expect($staff->fresh()->hasRole('moderator'))->toBeTrue();

    $grant = DB::table('role_scopes')->where('user_id', $staff->id)->where('role_id', $moderatorRoleId)->first();
    expect($grant)->not->toBeNull()
        ->and($grant->scope_kind)->toBe('none')
        ->and($grant->granted_by)->toBe($chief->id);
});

it('grants a country-scoped role to a staff account through the relation manager\'s grant action', function (): void {
    Role::findOrCreate('country_administrator', 'web');
    $chief = staffActor(['admin_panel_access', 'user.view', 'user.edit'], 'chief_administrator');
    $staff = User::factory()->create();
    $roleId = Role::findByName('country_administrator', 'web')->id;

    $languageId = DB::table('languages')->insertGetId([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $countryId = DB::table('countries')->insertGetId([
        'code' => 'MD', 'currency' => 'MDL', 'phone_code' => '+373', 'is_active' => true,
        'primary_language_id' => $languageId, 'created_at' => now(), 'updated_at' => now(),
    ]);

    Livewire::actingAs($chief)
        ->test(RoleGrantsRelationManager::class, ['ownerRecord' => $staff, 'pageClass' => EditStaff::class])
        ->callTableAction('grant', data: ['role_id' => $roleId, 'scope_kind' => 'country', 'scope_reference_id' => $countryId])
        ->assertNotified(__('panel.staff.grants.applied'));

    $grant = DB::table('role_scopes')->where('user_id', $staff->id)->where('role_id', $roleId)->first();
    expect($grant)->not->toBeNull()
        ->and($grant->scope_kind)->toBe('country')
        ->and($grant->scope_reference_id)->toBe($countryId);
});

it('hides the grant action from an administrator who is not the chief administrator', function (): void {
    Role::findOrCreate('moderator', 'web');
    $moderatorActor = staffActor(['admin_panel_access', 'user.view', 'user.edit'], 'moderator');
    $staff = User::factory()->create();

    Livewire::actingAs($moderatorActor)
        ->test(RoleGrantsRelationManager::class, ['ownerRecord' => $staff, 'pageClass' => EditStaff::class])
        ->assertTableActionHidden('grant');
});

it('revokes an active role grant through the relation manager\'s revoke action', function (): void {
    Role::findOrCreate('moderator', 'web');
    $chief = staffActor(['admin_panel_access', 'user.view', 'user.edit'], 'chief_administrator');
    $staff = User::factory()->create();
    app(RoleGrantService::class)->grantRole($staff, 'moderator', $chief);

    $grant = RoleScope::query()->where('user_id', $staff->id)->firstOrFail();

    Livewire::actingAs($chief)
        ->test(RoleGrantsRelationManager::class, ['ownerRecord' => $staff->fresh(), 'pageClass' => EditStaff::class])
        ->callTableAction('revoke', $grant)
        ->assertNotified(__('panel.staff.grants.applied'));

    expect($staff->fresh()->hasRole('moderator'))->toBeFalse();

    $revoked = DB::table('role_scopes')->where('id', $grant->id)->first();
    expect($revoked->revoked_at)->not->toBeNull()
        ->and($revoked->revoked_by)->toBe($chief->id);
});

it('refuses to revoke the chief administrator role from its last active holder through the relation manager, leaving the grant untouched', function (): void {
    $chief = staffActor(['admin_panel_access', 'user.view', 'user.edit'], 'chief_administrator');
    $grant = RoleScope::query()->where('user_id', $chief->id)->firstOrFail();

    Livewire::actingAs($chief)
        ->test(RoleGrantsRelationManager::class, ['ownerRecord' => $chief->fresh(), 'pageClass' => EditStaff::class])
        ->callTableAction('revoke', $grant)
        ->assertNotified(__('panel.staff.grants.revoke_refused'));

    expect($chief->fresh()->hasRole('chief_administrator'))->toBeTrue()
        ->and(DB::table('role_scopes')->where('id', $grant->id)->value('revoked_at'))->toBeNull();
});

/*
|--------------------------------------------------------------------------
| StaffResource — Labels, The Grantable-Role List, And Authorization
|--------------------------------------------------------------------------
*/

it('reads its navigation label from the translation catalog', function (): void {
    expect(StaffResource::getNavigationLabel())->toBe(__('panel.staff.title'));
});

it('excludes both object-side roles from the grantable-role picker, regardless of when they were seeded', function (): void {
    Role::findOrCreate('object_owner', 'web');
    Role::findOrCreate('object_staff_member', 'web');
    Role::findOrCreate('moderator', 'web');

    $options = StaffResource::grantableRoleOptions();
    $objectSideIds = Role::query()->whereIn('name', ['object_owner', 'object_staff_member'])->pluck('id')->all();

    expect(array_intersect(array_keys($options), $objectSideIds))->toBeEmpty()
        ->and($options)->toContain('moderator');
});

it('denies staff-resource authorization outright when no user is authenticated', function (): void {
    expect(StaffResource::getAuthorizationResponse('viewAny')->allowed())->toBeFalse();
});

it('authorizes viewing and deleting a specific staff record for the chief administrator, and falls back to viewAny for a recordless delete or an unrecognised action', function (): void {
    $chief = staffActor(['admin_panel_access', 'user.view', 'user.edit'], 'chief_administrator');
    $staff = User::factory()->create();

    $this->actingAs($chief);

    expect(StaffResource::getAuthorizationResponse('view', $staff)->allowed())->toBeTrue()
        ->and(StaffResource::getAuthorizationResponse('delete', $staff)->allowed())->toBeTrue()
        ->and(StaffResource::getAuthorizationResponse('deleteAny')->allowed())->toBeTrue()
        ->and(StaffResource::getAuthorizationResponse('restore')->allowed())->toBeTrue();
});
