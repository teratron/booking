<?php

declare(strict_types=1);

use App\Filament\Cabinet\Support\CabinetResource;
use App\Models\Object_;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Cabinet Foundation
|--------------------------------------------------------------------------
|
| The hard gate every later cabinet task depends on. Three things must hold
| before anything else in the phase can be trusted: the panel answers at its
| own address and authenticates against the one shared `User` table, never
| admitting a staff-only account and never admitting an owner into the admin
| panel; an owner — or a per-object staff member — can act on exactly the
| objects they own or are attached to, refused (not merely hidden) for every
| other object, at both the tenant-membership layer and the Policy layer;
| and the navigation-visibility helpers a later concrete resource will call
| resolve correctly against a real object type and the module ladder.
|
*/

function cabinetFixtureGeography(bool $hasRooms = false): array
{
    $languageId = DB::table('languages')->insertGetId([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $countryId = DB::table('countries')->insertGetId([
        'code' => 'MD', 'currency' => 'MDL', 'phone_code' => '+373',
        'primary_language_id' => $languageId, 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $levelId = DB::table('territory_levels')->insertGetId([
        'country_id' => $countryId, 'depth_rank' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $territoryId = DB::table('territories')->insertGetId([
        'country_id' => $countryId, 'level_id' => $levelId, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $typeId = DB::table('object_types')->insertGetId([
        'key' => 'accommodation', 'is_active' => true, 'has_rooms' => $hasRooms,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return compact('countryId', 'territoryId', 'typeId');
}

function makeCabinetObject(array $fixture, ?int $ownerId): Object_
{
    $object = new Object_;
    $object->ulid = (string) Str::ulid();
    $object->owner_id = $ownerId;
    $object->object_type_id = $fixture['typeId'];
    $object->territory_id = $fixture['territoryId'];
    $object->country_id = $fixture['countryId'];
    $object->status = 'draft';
    $object->save();

    return $object;
}

function cabinetRoleUser(string $roleKey, array $permissions): User
{
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $role = Role::findOrCreate($roleKey, 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $role->syncPermissions($permissions);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user->fresh();
}

/**
 * A minimal concrete resource, existing only to exercise `CabinetResource`'s
 * protected navigation-visibility helpers — the contract this task owns, not
 * any of Track B's later concrete resources.
 */
class CabinetFoundationTestResource extends CabinetResource
{
    public static function hasCapability(string $capability): bool
    {
        return static::currentObjectHasCapability($capability);
    }

    public static function moduleEnabled(string $moduleKey): bool
    {
        return static::currentObjectModuleEnabled($moduleKey);
    }
}

it('answers at its own configured address, distinct from the admin panel', function (): void {
    expect(config('booking.panels.cabinet.path'))->not->toBe(config('booking.panels.admin.path'));

    $this->get('/'.config('booking.panels.cabinet.path').'/login')->assertOk();
});

it('admits an owner to the cabinet and refuses the same account into the admin panel', function (): void {
    $fixture = cabinetFixtureGeography();
    $owner = cabinetRoleUser('object_owner_admission', ['object.view', 'object.edit', 'cabinet_access']);
    makeCabinetObject($fixture, $owner->id);

    $this->actingAs($owner)
        ->followingRedirects()
        ->get('/'.config('booking.panels.cabinet.path'))
        ->assertSuccessful();

    $this->actingAs($owner)
        ->get('/'.config('booking.panels.admin.path'))
        ->assertForbidden();
});

it('refuses a staff account holding only admin access into the cabinet', function (): void {
    $staff = cabinetRoleUser('chief_administrator_cabinet_refusal', ['admin_panel_access']);

    $this->actingAs($staff)
        ->get('/'.config('booking.panels.cabinet.path'))
        ->assertForbidden();
});

it("narrows an owner's tenants to exactly the objects they own, not another owner's", function (): void {
    $fixture = cabinetFixtureGeography();
    $ownerA = cabinetRoleUser('object_owner_narrow_a', ['object.view', 'object.edit', 'cabinet_access']);
    $ownerB = cabinetRoleUser('object_owner_narrow_b', ['object.view', 'object.edit', 'cabinet_access']);
    $objectA = makeCabinetObject($fixture, $ownerA->id);
    $objectB = makeCabinetObject($fixture, $ownerB->id);

    $panel = Filament::getPanel('cabinet');
    $tenants = $ownerA->getTenants($panel);

    expect($tenants)->toHaveCount(1)
        ->and($tenants->first()->is($objectA))->toBeTrue()
        ->and($ownerA->canAccessTenant($objectA))->toBeTrue()
        ->and($ownerA->canAccessTenant($objectB))->toBeFalse();
});

it('gives an owner with several objects every one as a switchable tenant, defaulting to the first', function (): void {
    $fixture = cabinetFixtureGeography();
    $owner = cabinetRoleUser('object_owner_multi', ['object.view', 'object.edit', 'cabinet_access']);
    $first = makeCabinetObject($fixture, $owner->id);
    $second = makeCabinetObject($fixture, $owner->id);

    $panel = Filament::getPanel('cabinet');

    expect($owner->getTenants($panel))->toHaveCount(2)
        ->and($owner->getDefaultTenant($panel)?->is($first))->toBeTrue();
});

it("refuses the bound Policy for an owner acting on another owner's object, at the record level", function (): void {
    $fixture = cabinetFixtureGeography();
    $ownerA = cabinetRoleUser('object_owner_policy_a', ['object.view', 'object.edit', 'cabinet_access']);
    $ownerB = cabinetRoleUser('object_owner_policy_b', ['object.view', 'object.edit', 'cabinet_access']);
    $objectA = makeCabinetObject($fixture, $ownerA->id);
    $objectB = makeCabinetObject($fixture, $ownerB->id);

    expect(Gate::forUser($ownerA)->allows('update', $objectA))->toBeTrue()
        ->and(Gate::forUser($ownerA)->allows('view', $objectB))->toBeFalse()
        ->and(Gate::forUser($ownerA)->allows('update', $objectB))->toBeFalse();
});

it("evaluates a staff member's capability entirely from their per-object grant, independent of any portal-wide permission", function (): void {
    $fixture = cabinetFixtureGeography();
    $owner = cabinetRoleUser('object_owner_staffed', ['object.view', 'object.edit', 'cabinet_access']);
    // Deliberately no object.edit at the role level — the migration's own
    // design intent is that a staff member's capability comes entirely from
    // the object_user row, never from the portal-wide permission system.
    $staffMember = cabinetRoleUser('object_staff_member_grant', ['object.view', 'cabinet_access']);
    $object = makeCabinetObject($fixture, $owner->id);

    DB::table('object_user')->insert([
        'object_id' => $object->id,
        'user_id' => $staffMember->id,
        'permissions' => json_encode(['object.view' => true, 'object.edit' => true]),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    expect($staffMember->canAccessTenant($object))->toBeTrue()
        ->and(Gate::forUser($staffMember)->allows('view', $object))->toBeTrue()
        ->and(Gate::forUser($staffMember)->allows('update', $object))->toBeTrue();

    $unrelated = makeCabinetObject($fixture, $owner->id);

    expect($staffMember->canAccessTenant($unrelated))->toBeFalse()
        ->and(Gate::forUser($staffMember)->allows('view', $unrelated))->toBeFalse();
});

it('refuses a staff member a permission their per-object grant row does not list', function (): void {
    $fixture = cabinetFixtureGeography();
    $owner = cabinetRoleUser('object_owner_limited', ['object.view', 'object.edit', 'cabinet_access']);
    $staffMember = cabinetRoleUser('object_staff_member_limited', ['object.view', 'cabinet_access']);
    $object = makeCabinetObject($fixture, $owner->id);

    DB::table('object_user')->insert([
        'object_id' => $object->id,
        'user_id' => $staffMember->id,
        'permissions' => json_encode(['object.view' => true]),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(Gate::forUser($staffMember)->allows('view', $object))->toBeTrue()
        ->and(Gate::forUser($staffMember)->allows('update', $object))->toBeFalse();
});

it("resolves the current object's declared structural capability for navigation gating", function (): void {
    $fixture = cabinetFixtureGeography(hasRooms: true);
    $owner = cabinetRoleUser('object_owner_capability', ['object.view', 'object.edit', 'cabinet_access']);
    $object = makeCabinetObject($fixture, $owner->id);

    Filament::setCurrentPanel('cabinet');
    Filament::setTenant($object, isQuiet: true);

    expect(CabinetFoundationTestResource::hasCapability('has_rooms'))->toBeTrue()
        ->and(CabinetFoundationTestResource::hasCapability('has_availability_status'))->toBeFalse();
});

it('resolves no module as enabled while no module is active in this configuration', function (): void {
    $fixture = cabinetFixtureGeography();
    $owner = cabinetRoleUser('object_owner_module', ['object.view', 'object.edit', 'cabinet_access']);
    $object = makeCabinetObject($fixture, $owner->id);

    Filament::setCurrentPanel('cabinet');
    Filament::setTenant($object, isQuiet: true);

    expect(CabinetFoundationTestResource::moduleEnabled('booking'))->toBeFalse();
});

it('renders the account Settings page for an authenticated owner, the one cabinet route with no tenant segment', function (): void {
    $fixture = cabinetFixtureGeography();
    $owner = cabinetRoleUser('object_owner_settings_render', ['object.view', 'object.edit', 'cabinet_access']);
    makeCabinetObject($fixture, $owner->id);

    $response = $this->actingAs($owner)->get(route('filament.cabinet.auth.profile'));

    $response->assertSuccessful()
        ->assertSee($owner->name)
        ->assertSee($owner->email);
});

it('resolves the navigation-visibility helpers to false with no tenant bound', function (): void {
    Filament::setCurrentPanel('cabinet');
    Filament::setTenant(null, isQuiet: true);

    expect(CabinetFoundationTestResource::hasCapability('has_rooms'))->toBeFalse()
        ->and(CabinetFoundationTestResource::moduleEnabled('booking'))->toBeFalse();
});
