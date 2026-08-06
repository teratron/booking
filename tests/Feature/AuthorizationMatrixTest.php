<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Authorization\ScopeAuthorizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Authorization Test Matrix
|--------------------------------------------------------------------------
|
| Proves T-1D01's ScopeAuthorizer denies as well as it allows, across every
| scope kind and a representative permission verb set, against a fixture
| spanning two countries, nested territories (three levels deep), and two
| object categories — not a single-country, single-permission shortcut.
| The asymmetry is the point: an allow-path test passes trivially, and the
| failure this matrix exists to catch — a country administrator reaching
| another country's object — never surfaces in one.
|
*/

const OBJECT_VERBS = ['view', 'create', 'edit', 'publish', 'delete', 'export'];

function seedAuthMatrixFixture(): array
{
    $languageId = DB::table('languages')->insertGetId([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $countryMd = DB::table('countries')->insertGetId([
        'code' => 'MD', 'currency' => 'MDL', 'phone_code' => '+373',
        'primary_language_id' => $languageId, 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $countryUa = DB::table('countries')->insertGetId([
        'code' => 'UA', 'currency' => 'UAH', 'phone_code' => '+380',
        'primary_language_id' => $languageId, 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $levelMd = DB::table('territory_levels')->insertGetId([
        'country_id' => $countryMd, 'depth_rank' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $levelUa = DB::table('territory_levels')->insertGetId([
        'country_id' => $countryUa, 'depth_rank' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);

    // Three levels deep in MD: region -> city -> resort.
    $mdRegion = DB::table('territories')->insertGetId([
        'country_id' => $countryMd, 'level_id' => $levelMd, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $mdCity = DB::table('territories')->insertGetId([
        'country_id' => $countryMd, 'level_id' => $levelMd, 'parent_id' => $mdRegion, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $mdResort = DB::table('territories')->insertGetId([
        'country_id' => $countryMd, 'level_id' => $levelMd, 'parent_id' => $mdCity, 'created_at' => now(), 'updated_at' => now(),
    ]);
    // A sibling region in the same country, outside the first region's subtree.
    $mdSiblingRegion = DB::table('territories')->insertGetId([
        'country_id' => $countryMd, 'level_id' => $levelMd, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $uaRegion = DB::table('territories')->insertGetId([
        'country_id' => $countryUa, 'level_id' => $levelUa, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $categoryHotel = DB::table('object_types')->insertGetId([
        'key' => 'hotel', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $categoryRestaurant = DB::table('object_types')->insertGetId([
        'key' => 'restaurant', 'created_at' => now(), 'updated_at' => now(),
    ]);

    return compact(
        'countryMd', 'countryUa', 'mdRegion', 'mdCity', 'mdResort', 'mdSiblingRegion',
        'uaRegion', 'categoryHotel', 'categoryRestaurant'
    );
}

function grantMatrixRole(User $user, string $permissionKey, string $scopeKind, ?int $scopeReferenceId, User $grantedBy): void
{
    Permission::findOrCreate($permissionKey, 'web');
    $role = Role::findOrCreate("matrix_role_{$permissionKey}_{$scopeKind}_{$scopeReferenceId}", 'web');
    $role->givePermissionTo($permissionKey);
    $user->assignRole($role->name);

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

test('an unrestricted grant allows every scope combination for its verb', function (string $verb): void {
    $fixture = seedAuthMatrixFixture();
    $admin = User::factory()->create();
    $user = User::factory()->create();
    grantMatrixRole($user, "object.{$verb}", 'none', null, $admin);

    $authorizer = app(ScopeAuthorizer::class);

    expect($authorizer->authorize($user, "object.{$verb}", countryId: $fixture['countryMd']))->toBeTrue();
    expect($authorizer->authorize($user, "object.{$verb}", countryId: $fixture['countryUa']))->toBeTrue();
    expect($authorizer->authorize($user, "object.{$verb}", territoryId: $fixture['mdResort']))->toBeTrue();
    expect($authorizer->authorize($user, "object.{$verb}", categoryId: $fixture['categoryRestaurant']))->toBeTrue();
})->with(OBJECT_VERBS);

test('a country-scoped grant allows its own country and denies the other, for every verb', function (string $verb): void {
    $fixture = seedAuthMatrixFixture();
    $admin = User::factory()->create();
    $user = User::factory()->create();
    grantMatrixRole($user, "object.{$verb}", 'country', $fixture['countryMd'], $admin);

    $authorizer = app(ScopeAuthorizer::class);

    expect($authorizer->authorize($user, "object.{$verb}", countryId: $fixture['countryMd']))->toBeTrue();
    expect($authorizer->authorize($user, "object.{$verb}", countryId: $fixture['countryUa']))
        ->toBeFalse("A {$verb} grant scoped to MD must not reach a UA-country target.");
})->with(OBJECT_VERBS);

test('a category-scoped grant allows its own category and denies the other, for every verb', function (string $verb): void {
    $fixture = seedAuthMatrixFixture();
    $admin = User::factory()->create();
    $user = User::factory()->create();
    grantMatrixRole($user, "object.{$verb}", 'category', $fixture['categoryHotel'], $admin);

    $authorizer = app(ScopeAuthorizer::class);

    expect($authorizer->authorize($user, "object.{$verb}", categoryId: $fixture['categoryHotel']))->toBeTrue();
    expect($authorizer->authorize($user, "object.{$verb}", categoryId: $fixture['categoryRestaurant']))
        ->toBeFalse("A {$verb} grant scoped to hotel must not reach a restaurant-category target.");
})->with(OBJECT_VERBS);

test('a territory-scoped grant reaches every descendant of its node but no sibling subtree or other country, for every verb', function (string $verb): void {
    $fixture = seedAuthMatrixFixture();
    $admin = User::factory()->create();
    $user = User::factory()->create();
    grantMatrixRole($user, "object.{$verb}", 'territory', $fixture['mdRegion'], $admin);

    $authorizer = app(ScopeAuthorizer::class);

    expect($authorizer->authorize($user, "object.{$verb}", territoryId: $fixture['mdRegion']))->toBeTrue();
    expect($authorizer->authorize($user, "object.{$verb}", territoryId: $fixture['mdCity']))->toBeTrue();
    expect($authorizer->authorize($user, "object.{$verb}", territoryId: $fixture['mdResort']))
        ->toBeTrue('A territory grant must reach a grandchild node, not only direct children.');
    expect($authorizer->authorize($user, "object.{$verb}", territoryId: $fixture['mdSiblingRegion']))
        ->toBeFalse('A territory grant on one region must not reach a sibling region in the same country.');
    expect($authorizer->authorize($user, "object.{$verb}", territoryId: $fixture['uaRegion']))
        ->toBeFalse('A territory grant scoped to an MD region must not reach a UA territory.');
})->with(OBJECT_VERBS);

test('a permission the user was never granted is denied regardless of scope kind', function (): void {
    $user = User::factory()->create();
    $authorizer = app(ScopeAuthorizer::class);

    // No role, no permission, no role_scopes row at all — the baseline
    // every other case in this matrix is measured against.
    expect($authorizer->authorize($user, 'object.view', countryId: 1, territoryId: 1, categoryId: 1))->toBeFalse();
});
