<?php

declare(strict_types=1);

use App\Models\User;
use App\Policies\StaffPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| StaffPolicy — chief_administrator-only, deliberately not permission-based
|--------------------------------------------------------------------------
|
| Every ability gates on hasRole('chief_administrator') alone, per the
| policy's own docblock: StaffResource and OwnerResource both expose the
| User model, and reusing UserPolicy's user.* permission checks here would
| let a country or region administrator holding user.edit reach staff
| administration. The permission-holder case below is the one that would
| silently defeat that boundary if this policy were ever refactored to
| check a permission instead of the role directly.
|
*/

it('grants every StaffPolicy ability to a chief_administrator', function (): void {
    Role::findOrCreate('chief_administrator', 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $chiefAdministrator = User::factory()->create();
    $chiefAdministrator->assignRole('chief_administrator');

    $target = User::factory()->create();
    $policy = app(StaffPolicy::class);

    expect($policy->viewAny($chiefAdministrator))->toBeTrue()
        ->and($policy->view($chiefAdministrator, $target))->toBeTrue()
        ->and($policy->create($chiefAdministrator))->toBeTrue()
        ->and($policy->update($chiefAdministrator, $target))->toBeTrue()
        ->and($policy->delete($chiefAdministrator, $target))->toBeTrue();
});

it('denies every StaffPolicy ability to a user holding no role at all', function (): void {
    $bystander = User::factory()->create();
    $target = User::factory()->create();
    $policy = app(StaffPolicy::class);

    expect($policy->viewAny($bystander))->toBeFalse()
        ->and($policy->view($bystander, $target))->toBeFalse()
        ->and($policy->create($bystander))->toBeFalse()
        ->and($policy->update($bystander, $target))->toBeFalse()
        ->and($policy->delete($bystander, $target))->toBeFalse();
});

it('denies every StaffPolicy ability to a user holding user.edit but not the chief_administrator role', function (): void {
    // The exact scenario the policy's docblock names: a country or region
    // administrator holds user.edit for owner management under UserPolicy,
    // but that permission must not carry over into staff administration —
    // this policy never inspects permissions at all, only the role itself.
    Permission::findOrCreate('user.edit', 'web');
    $regionAdministratorRole = Role::findOrCreate('region_administrator', 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $regionAdministratorRole->syncPermissions(['user.edit']);

    $regionAdministrator = User::factory()->create();
    $regionAdministrator->assignRole($regionAdministratorRole);

    $target = User::factory()->create();
    $policy = app(StaffPolicy::class);

    expect($regionAdministrator->can('user.edit'))->toBeTrue();

    expect($policy->viewAny($regionAdministrator))->toBeFalse()
        ->and($policy->view($regionAdministrator, $target))->toBeFalse()
        ->and($policy->create($regionAdministrator))->toBeFalse()
        ->and($policy->update($regionAdministrator, $target))->toBeFalse()
        ->and($policy->delete($regionAdministrator, $target))->toBeFalse();
});

it('denies every StaffPolicy ability to a user holding an unrelated role', function (): void {
    Role::findOrCreate('moderator', 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $moderator = User::factory()->create();
    $moderator->assignRole('moderator');

    $target = User::factory()->create();
    $policy = app(StaffPolicy::class);

    expect($policy->viewAny($moderator))->toBeFalse()
        ->and($policy->view($moderator, $target))->toBeFalse()
        ->and($policy->create($moderator))->toBeFalse()
        ->and($policy->update($moderator, $target))->toBeFalse()
        ->and($policy->delete($moderator, $target))->toBeFalse();
});
