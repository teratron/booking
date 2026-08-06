<?php

declare(strict_types=1);

use App\Exceptions\UnrevocableGrantException;
use App\Models\User;
use App\Services\Authorization\RoleGrantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
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

    expect(fn () => app(RoleGrantService::class)->revokeRole($solo, 'chief_administrator'))
        ->toThrow(UnrevocableGrantException::class);

    expect($solo->fresh()->hasRole('chief_administrator'))->toBeTrue();
});

test('the chief administrator role can be revoked when another holder remains', function (): void {
    $first = User::where('email', 'test@example.com')->first();
    $second = User::factory()->create();
    $second->assignRole('chief_administrator');

    app(RoleGrantService::class)->revokeRole($first, 'chief_administrator');

    expect($first->fresh()->hasRole('chief_administrator'))->toBeFalse();
    expect($second->fresh()->hasRole('chief_administrator'))->toBeTrue();
});
