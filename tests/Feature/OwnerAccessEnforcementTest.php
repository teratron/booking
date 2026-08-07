<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    $role = Role::findOrCreate('object_owner', 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $role->syncPermissions(['cabinet_access']);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user->fresh();
}

it('admits an owner account holding cabinet access', function (): void {
    $owner = ownerUser();

    $this->actingAs($owner)
        ->get(cabinetPanelUrl())
        ->assertSuccessful();
});

it('refuses a blocked account on its very next request, without a fresh sign-in', function (): void {
    $owner = ownerUser();

    $this->actingAs($owner)
        ->get(cabinetPanelUrl())
        ->assertSuccessful();

    $owner->forceFill(['blocked_at' => now()])->save();

    $this->get(cabinetPanelUrl())
        ->assertForbidden();
});
