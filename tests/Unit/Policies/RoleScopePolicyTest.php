<?php

declare(strict_types=1);

use App\Models\RoleScope;
use App\Models\User;
use App\Policies\RoleScopePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| RoleScopePolicy — viewAny / view
|--------------------------------------------------------------------------
|
| Reached only through the staff-administration screen's own relation
| manager, which StaffResource already restricts to chief_administrator
| alone — an inherently unrestricted role. So the policy deliberately
| checks the same `user.edit` grant through ScopedPolicy::authorize()
| with no scope target, which resolves true only for an unrestricted
| ('none'-kind) grant: any restricted (country/territory/category) grant
| of `user.edit` is refused, because no target axis is ever supplied for
| it to match against. That is the intended shape, not a bug — a
| country-scoped administrator has no legitimate reason to reach this
| relation manager in the first place, since StaffResource never lets
| them past its own gate.
|
*/

function roleScopePolicyActor(string $scopeKind, ?int $scopeReferenceId, string $roleKey): User
{
    Permission::findOrCreate('user.edit', 'web');
    Permission::findOrCreate('admin_panel_access', 'web');

    $role = Role::findOrCreate($roleKey, 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $role->syncPermissions(['user.edit', 'admin_panel_access']);

    $user = User::factory()->create();
    $user->assignRole($role);

    DB::table('role_scopes')->insert([
        'user_id' => $user->id, 'role_id' => $role->id,
        'scope_kind' => $scopeKind, 'scope_reference_id' => $scopeReferenceId,
        'granted_by' => $user->id, 'granted_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return $user->fresh();
}

function roleScopePolicyTargetRow(): RoleScope
{
    // Deliberately unrelated to any actor under test: RoleScopePolicy never
    // inspects the target row's own user, role, or scope — its decision
    // comes entirely from the acting user's own grant — so reusing an
    // actor's own user/role here would silently add a second role_scopes
    // row for that actor and change what is being tested.
    $grantee = User::factory()->create();
    $role = Role::findOrCreate('role_scope_policy_target_role', 'web');

    /** @var RoleScope $scope */
    $scope = RoleScope::query()->create([
        'user_id' => $grantee->id,
        'role_id' => $role->id,
        'scope_kind' => 'none',
        'scope_reference_id' => null,
        'granted_by' => $grantee->id,
        'granted_at' => now(),
    ]);

    return $scope;
}

it('grants RoleScopePolicy viewAny and view to a chief_administrator holding an unrestricted user.edit grant', function (): void {
    $chief = roleScopePolicyActor('none', null, 'chief_administrator');
    $target = roleScopePolicyTargetRow();

    $policy = app(RoleScopePolicy::class);

    expect($policy->viewAny($chief))->toBeTrue()
        ->and($policy->view($chief, $target))->toBeTrue();
});

it('refuses RoleScopePolicy viewAny and view to a user.edit holder whose grant is country-scoped, not unrestricted', function (): void {
    $restricted = roleScopePolicyActor('country', 7, 'country_scoped_editor');
    $target = roleScopePolicyTargetRow();

    $policy = app(RoleScopePolicy::class);

    // ScopedPolicy::authorize() is called with no country/territory/category
    // target here, so a restricted grant can never match — even one whose
    // reference happens to be the same country the target row would belong
    // to, since that axis is never passed in the first place.
    expect($policy->viewAny($restricted))->toBeFalse()
        ->and($policy->view($restricted, $target))->toBeFalse();
});

it('denies RoleScopePolicy viewAny and view outright to a user holding no user.edit grant at all', function (): void {
    $bystander = User::factory()->create();
    $target = roleScopePolicyTargetRow();

    $policy = app(RoleScopePolicy::class);

    expect($policy->viewAny($bystander))->toBeFalse()
        ->and($policy->view($bystander, $target))->toBeFalse();
});
