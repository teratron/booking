<?php

declare(strict_types=1);

use App\Models\User;
use App\Policies\ScopedPolicy;
use App\Services\Authorization\ScopeAuthorizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Scope Authorizer
|--------------------------------------------------------------------------
|
| The asymmetry matters more than the allow path: an in-scope target being
| allowed is the easy half, and every case below also proves the matching
| out-of-scope target is denied — the failure a naive test suite would
| never surface. The territory case is proven at two levels of descent, not
| one, since "a region administrator governs every city beneath them" is
| exactly the claim a single-level check would falsely satisfy.
|
*/

function grantScopedRole(User $user, string $roleKey, string $permissionKey, string $scopeKind, ?int $scopeReferenceId, User $grantedBy): void
{
    Permission::findOrCreate($permissionKey, 'web');
    $role = Role::findOrCreate($roleKey, 'web');
    $role->givePermissionTo($permissionKey);

    $user->assignRole($roleKey);

    DB::table('role_scopes')->insert([
        'user_id' => $user->id,
        'role_id' => $role->id,
        'scope_kind' => $scopeKind,
        'scope_reference_id' => $scopeReferenceId,
        'granted_by' => $grantedBy->id,
        'granted_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

test('an unrestricted grant covers any target', function (): void {
    $admin = User::factory()->create();
    $user = User::factory()->create();
    grantScopedRole($user, 'unrestricted_role', 'demo.view', 'none', null, $admin);

    $authorizer = app(ScopeAuthorizer::class);

    expect($authorizer->authorize($user, 'demo.view', countryId: 999))->toBeTrue();
    expect($authorizer->authorize($user, 'demo.view'))->toBeTrue();
});

test('a country-scoped grant covers its own country and denies another', function (): void {
    $admin = User::factory()->create();
    $user = User::factory()->create();
    grantScopedRole($user, 'country_role', 'demo.view', 'country', 1, $admin);

    $authorizer = app(ScopeAuthorizer::class);

    expect($authorizer->authorize($user, 'demo.view', countryId: 1))->toBeTrue();
    expect($authorizer->authorize($user, 'demo.view', countryId: 2))->toBeFalse();
});

test('a territory-scoped grant covers every descendant at any depth and denies a sibling subtree', function (): void {
    $admin = User::factory()->create();
    $user = User::factory()->create();

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

    $region = DB::table('territories')->insertGetId([
        'country_id' => $countryId, 'level_id' => $levelId, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $city = DB::table('territories')->insertGetId([
        'country_id' => $countryId, 'level_id' => $levelId, 'parent_id' => $region, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $resort = DB::table('territories')->insertGetId([
        'country_id' => $countryId, 'level_id' => $levelId, 'parent_id' => $city, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $siblingRegion = DB::table('territories')->insertGetId([
        'country_id' => $countryId, 'level_id' => $levelId, 'created_at' => now(), 'updated_at' => now(),
    ]);

    grantScopedRole($user, 'territory_role', 'demo.view', 'territory', $region, $admin);

    $authorizer = app(ScopeAuthorizer::class);

    expect($authorizer->authorize($user, 'demo.view', territoryId: $region))->toBeTrue();
    expect($authorizer->authorize($user, 'demo.view', territoryId: $city))->toBeTrue();
    // Two levels down — a single-level parent check would wrongly deny this.
    expect($authorizer->authorize($user, 'demo.view', territoryId: $resort))->toBeTrue();
    expect($authorizer->authorize($user, 'demo.view', territoryId: $siblingRegion))->toBeFalse();
});

test('a category-scoped grant covers its own category and denies another', function (): void {
    $admin = User::factory()->create();
    $user = User::factory()->create();
    grantScopedRole($user, 'category_role', 'demo.view', 'category', 5, $admin);

    $authorizer = app(ScopeAuthorizer::class);

    expect($authorizer->authorize($user, 'demo.view', categoryId: 5))->toBeTrue();
    expect($authorizer->authorize($user, 'demo.view', categoryId: 6))->toBeFalse();
});

test('a user with no grant of the permission is denied', function (): void {
    $user = User::factory()->create();

    expect(app(ScopeAuthorizer::class)->authorize($user, 'demo.view', countryId: 1))->toBeFalse();
});

test('a role granting the permission with no role_scopes row covers nothing', function (): void {
    // Deliberately assigns the role through spatie directly, bypassing the
    // granting path that would also write a role_scopes row — proves the
    // resolver does not treat an unscoped-by-omission grant as unrestricted.
    Permission::findOrCreate('demo.view', 'web');
    $role = Role::findOrCreate('bare_role', 'web');
    $role->givePermissionTo('demo.view');

    $user = User::factory()->create();
    $user->assignRole('bare_role');

    expect($user->can('demo.view'))->toBeTrue();
    expect(app(ScopeAuthorizer::class)->authorize($user, 'demo.view', countryId: 1))->toBeFalse();
});

test('ScopedPolicy delegates its decision to the injected authorizer', function (): void {
    $admin = User::factory()->create();
    $user = User::factory()->create();
    grantScopedRole($user, 'policy_role', 'demo.view', 'country', 1, $admin);

    $policy = new class(app(ScopeAuthorizer::class)) extends ScopedPolicy
    {
        public function view(User $user, int $countryId): bool
        {
            return $this->authorize($user, 'demo.view', countryId: $countryId);
        }
    };

    expect($policy->view($user, 1))->toBeTrue();
    expect($policy->view($user, 2))->toBeFalse();
});
