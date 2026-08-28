<?php

declare(strict_types=1);

use App\Models\ApiClient;
use App\Models\ApiToken;
use App\Models\User;
use App\Policies\ApiTokenPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| ApiTokenPolicy — api.view / api.create / api.edit / api.delete
|--------------------------------------------------------------------------
|
| A token is portal-wide administration, not scoped to a country,
| territory, or category — the policy's own docblock says it mirrors
| ApiClientPolicy exactly, and every ability below resolves from a flat
| api.* permission with no ownership axis of its own. A token belonging to
| a user other than the acting staff member is not a distinct case here:
| unlike an owner-scoped resource, nothing in this policy reads the
| token's owning client or creator, so the same permission check must
| resolve identically no matter which client the token belongs to.
|
*/

function apiTokenPolicyActor(array $permissions, string $roleKey): User
{
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $role = Role::findOrCreate($roleKey, 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $role->syncPermissions($permissions);

    $user = User::factory()->create();
    $user->assignRole($role);

    DB::table('role_scopes')->insert([
        'user_id' => $user->id, 'role_id' => $role->id,
        'scope_kind' => 'none', 'scope_reference_id' => null,
        'granted_by' => $user->id, 'granted_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return $user->fresh();
}

function apiTokenPolicyToken(User $owner): ApiToken
{
    $client = ApiClient::query()->create([
        'name' => 'Acme Travel',
        'is_active' => true,
        'created_by' => $owner->id,
    ]);

    /** @var ApiToken $token */
    $token = $client->createToken('primary')->accessToken;

    return $token;
}

it('resolves every ApiTokenPolicy action from the acting user\'s own api.* grants', function (): void {
    $owner = User::factory()->create();
    $token = apiTokenPolicyToken($owner);

    $permitted = apiTokenPolicyActor(
        ['admin_panel_access', 'api.view', 'api.create', 'api.edit', 'api.delete'],
        'api_token_policy_permitted',
    );
    $refused = apiTokenPolicyActor(['admin_panel_access'], 'api_token_policy_refused');

    $policy = app(ApiTokenPolicy::class);

    expect($policy->viewAny($permitted))->toBeTrue()
        ->and($policy->view($permitted, $token))->toBeTrue()
        ->and($policy->create($permitted))->toBeTrue()
        ->and($policy->update($permitted, $token))->toBeTrue()
        ->and($policy->delete($permitted, $token))->toBeTrue();

    expect($policy->viewAny($refused))->toBeFalse()
        ->and($policy->view($refused, $token))->toBeFalse()
        ->and($policy->create($refused))->toBeFalse()
        ->and($policy->update($refused, $token))->toBeFalse()
        ->and($policy->delete($refused, $token))->toBeFalse();
});

it('grants ApiTokenPolicy read access on api.view alone, without also granting the write abilities', function (): void {
    $owner = User::factory()->create();
    $token = apiTokenPolicyToken($owner);

    $readOnly = apiTokenPolicyActor(['admin_panel_access', 'api.view'], 'api_token_policy_read_only');

    $policy = app(ApiTokenPolicy::class);

    expect($policy->viewAny($readOnly))->toBeTrue()
        ->and($policy->view($readOnly, $token))->toBeTrue()
        ->and($policy->create($readOnly))->toBeFalse()
        ->and($policy->update($readOnly, $token))->toBeFalse()
        ->and($policy->delete($readOnly, $token))->toBeFalse();
});

it('resolves ApiTokenPolicy abilities identically regardless of which client owns the target token', function (): void {
    $firstOwner = User::factory()->create();
    $secondOwner = User::factory()->create();

    $tokenOfFirstClient = apiTokenPolicyToken($firstOwner);
    $tokenOfSecondClient = apiTokenPolicyToken($secondOwner);

    $staff = apiTokenPolicyActor(
        ['admin_panel_access', 'api.view', 'api.edit', 'api.delete'],
        'api_token_policy_cross_client',
    );

    $policy = app(ApiTokenPolicy::class);

    // No ownership axis exists on this policy — a staff member holding
    // api.* may act on a token issued to any client, not just one they
    // created themselves.
    expect($policy->view($staff, $tokenOfFirstClient))->toBeTrue()
        ->and($policy->view($staff, $tokenOfSecondClient))->toBeTrue()
        ->and($policy->update($staff, $tokenOfFirstClient))->toBeTrue()
        ->and($policy->update($staff, $tokenOfSecondClient))->toBeTrue()
        ->and($policy->delete($staff, $tokenOfFirstClient))->toBeTrue()
        ->and($policy->delete($staff, $tokenOfSecondClient))->toBeTrue();
});

it('denies every ApiTokenPolicy ability outright when the acting user holds no api.* grant at all', function (): void {
    $owner = User::factory()->create();
    $token = apiTokenPolicyToken($owner);

    $bystander = User::factory()->create();

    $policy = app(ApiTokenPolicy::class);

    expect($policy->viewAny($bystander))->toBeFalse()
        ->and($policy->view($bystander, $token))->toBeFalse()
        ->and($policy->create($bystander))->toBeFalse()
        ->and($policy->update($bystander, $token))->toBeFalse()
        ->and($policy->delete($bystander, $token))->toBeFalse();
});
