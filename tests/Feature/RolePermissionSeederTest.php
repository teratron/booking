<?php

declare(strict_types=1);

use App\Exceptions\UnrevocableGrantException;
use App\Models\User;
use App\Services\Authorization\RoleGrantService;
use App\Services\Authorization\ScopeAuthorizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Roles & Permissions Seeder
|--------------------------------------------------------------------------
|
| Two invariants this task's Verify line names directly: every role in the
| launch set is a real seeded record carrying a non-empty permission set,
| and the chief administrator grant cannot be revoked through the normal
| path when doing so would leave the role with no holder. The second half
| is proven both ways — refused when it is the last holder, permitted when
| it is not — the same "prove it can fail, prove it can succeed" standard
| every constraint in this project is held to.
|
*/

beforeEach(function (): void {
    Artisan::call('db:seed');
});

test('every launch role exists as a seeded record with a non-empty permission set', function (string $roleKey): void {
    $role = Role::where('name', $roleKey)->first();

    expect($role)->not->toBeNull("Expected role '{$roleKey}' to be seeded.");
    expect($role->permissions)->not->toBeEmpty("Expected role '{$roleKey}' to carry a non-empty permission set.");
})->with([
    'chief_administrator', 'country_administrator', 'region_administrator', 'moderator',
    'content_manager', 'seo_specialist', 'advertising_manager', 'finance_manager',
    'technical_support', 'object_owner', 'object_staff_member',
]);

test('the chief administrator role cannot be revoked from its last remaining holder', function (): void {
    $solo = User::factory()->create();
    $solo->assignRole('chief_administrator');

    // The seeder's own "Test User" also holds the role — remove it first so
    // this test genuinely exercises the last-holder case, not a coincidence
    // of seed data shape.
    User::where('email', 'test@example.com')->first()->removeRole('chief_administrator');

    expect(fn () => app(RoleGrantService::class)->revokeRole($solo, 'chief_administrator', $solo))
        ->toThrow(UnrevocableGrantException::class);

    expect($solo->fresh()->hasRole('chief_administrator'))->toBeTrue();
});

test('the chief administrator role can be revoked when another holder remains', function (): void {
    $first = User::where('email', 'test@example.com')->first();
    $second = User::factory()->create();
    $second->assignRole('chief_administrator');

    app(RoleGrantService::class)->revokeRole($first, 'chief_administrator', $second);

    expect($first->fresh()->hasRole('chief_administrator'))->toBeFalse();
    expect($second->fresh()->hasRole('chief_administrator'))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Scope Grant — RoleGrantService::grantRole()
|--------------------------------------------------------------------------
|
| A role assigned through Spatie's bare assignRole() carries a permission
| with no scope decision behind it — ScopeAuthorizer reads that as "reaches
| no axis" and every scoped back-office resource fails closed. grantRole()
| exists so the role and its scope are written together, in one call, and
| can never drift apart the way the seeder's own bare assignRole() call
| once did.
|
*/

test('grantRole() writes both the Spatie assignment and a matching role_scopes row', function (): void {
    $actor = User::factory()->create();
    $subject = User::factory()->create();

    app(RoleGrantService::class)->grantRole($subject, 'moderator', $actor);

    expect($subject->fresh()->hasRole('moderator'))->toBeTrue();

    $roleId = Role::where('name', 'moderator')->value('id');
    $row = DB::table('role_scopes')
        ->where('user_id', $subject->id)
        ->where('role_id', $roleId)
        ->first();

    expect($row)->not->toBeNull()
        ->and($row->scope_kind)->toBe('none')
        ->and($row->scope_reference_id)->toBeNull()
        ->and($row->granted_by)->toBe($actor->id);
});

test('grantRole() records a bounded scope when one is given', function (): void {
    $actor = User::factory()->create();
    $subject = User::factory()->create();

    app(RoleGrantService::class)->grantRole($subject, 'country_administrator', $actor, 'country', 7);

    $roleId = Role::where('name', 'country_administrator')->value('id');
    $row = DB::table('role_scopes')
        ->where('user_id', $subject->id)
        ->where('role_id', $roleId)
        ->first();

    expect($row->scope_kind)->toBe('country')
        ->and($row->scope_reference_id)->toBe(7);
});

test('the seeded chief administrator has an unrestricted scope grant, not just the role', function (): void {
    $admin = User::where('email', 'test@example.com')->firstOrFail();

    $constraint = app(ScopeAuthorizer::class)->constraintFor($admin, 'object.view');

    expect($constraint->isUnrestricted)->toBeTrue();
});
