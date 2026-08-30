<?php

declare(strict_types=1);

use App\Models\Object_;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Owner Account Access Enforcement
|--------------------------------------------------------------------------
|
| `User::canAccessPanel()` refuses a blocked account ahead of the ordinary
| permission check. The requirement that actually matters is not that the
| check exists, but that Filament re-evaluates it on every panel request —
| a version that cached the authorization decision for the session lifetime
| would let a just-blocked account keep working until it signed out on its
| own. Proven here by blocking the account mid-session, between two
| requests sharing the same authenticated session, rather than asserting
| against the method in isolation.
|
*/

function cabinetPanelUrl(string $path = ''): string
{
    return '/'.config('booking.panels.cabinet.path').$path;
}

function ownerUser(): User
{
    Permission::findOrCreate('cabinet_access', 'web');
    Permission::findOrCreate('object.view', 'web');
    Permission::findOrCreate('object.edit', 'web');

    $role = Role::findOrCreate('object_owner', 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $role->syncPermissions(['cabinet_access', 'object.view', 'object.edit']);

    $user = User::factory()->create();
    $user->assignRole($role);
    $user = $user->fresh();

    // The cabinet is Filament tenant-scoped to Object_: reaching
    // any page past sign-in requires at least one object to land on, the
    // same way an admin account requires a permission before its panel
    // renders anything. Object assignment happens once, at owner creation
    // through the admin panel's Owners resource — this fixture mirrors that,
    // rather than testing a bare account no real workflow ever leaves behind.
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
        'key' => 'accommodation', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $object = new Object_;
    $object->ulid = (string) Str::ulid();
    $object->owner_id = $user->id;
    $object->object_type_id = $typeId;
    $object->territory_id = $territoryId;
    $object->country_id = $countryId;
    $object->status = 'draft';
    $object->save();

    return $user;
}

it('admits an owner account holding cabinet access', function (): void {
    $owner = ownerUser();

    // The bare panel path redirects to the owner's default tenant (their one
    // object) before rendering anything — following that chain is what
    // "admitted" actually means once the panel is tenant-scoped.
    $this->actingAs($owner)
        ->followingRedirects()
        ->get(cabinetPanelUrl())
        ->assertSuccessful();
});

it('refuses a blocked account on its very next request, without a fresh sign-in', function (): void {
    $owner = ownerUser();

    $this->actingAs($owner)
        ->followingRedirects()
        ->get(cabinetPanelUrl())
        ->assertSuccessful();

    $owner->forceFill(['blocked_at' => now()])->save();

    // Refused ahead of tenant resolution — canAccessPanel() runs in the
    // panel's own auth middleware, before the tenant-redirect route ever
    // executes, so this stays a direct 403 rather than a redirect.
    $this->get(cabinetPanelUrl())
        ->assertForbidden();
});
