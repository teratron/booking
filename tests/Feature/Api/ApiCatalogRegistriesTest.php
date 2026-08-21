<?php

declare(strict_types=1);

use App\Models\ApiClient;
use App\Models\Module;
use App\Models\User;
use App\Services\Api\ApiTokenService;
use App\Services\Modules\ModuleAdministrator;
use App\Support\Api\ApiResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Public API — Object Type and Amenity Registries
|--------------------------------------------------------------------------
|
| Two read-only global registries: object types (the catalog's category
| axis, so a token's category scope narrows it) and amenities (owned by no
| country or category at all, so no token scope narrows it — every active
| row is visible to any token holding the ability). Both are gated the same
| way every other v1 endpoint is — module enabled, bearer token carrying
| the resource's own ability — proven once here rather than assumed from
| the objects/countries coverage elsewhere.
|
*/

function acrEnableApiModule(): void
{
    $moduleId = DB::table('modules')->insertGetId([
        'key' => 'api', 'default_state' => 'disabled', 'scopable_levels' => json_encode(['portal']),
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $module = Module::query()->findOrFail($moduleId);
    $actor = User::factory()->create();

    app(ModuleAdministrator::class)->setState($module, 'portal', null, true, $actor);
}

function acrPrimaryLanguage(): void
{
    DB::table('languages')->insert([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** @param  array<string, mixed>  $overrides */
function acrObjectType(string $key, string $name, array $overrides = []): int
{
    $typeId = DB::table('object_types')->insertGetId(array_merge([
        'key' => $key, 'is_active' => true, 'attribute_schema' => json_encode([]),
        'created_at' => now(), 'updated_at' => now(),
    ], $overrides));

    DB::table('object_type_translations')->insert([
        'object_type_id' => $typeId, 'locale' => 'en', 'name' => $name, 'slug' => $key,
        'needs_review' => false, 'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);

    return $typeId;
}

function acrAmenityGroup(string $name): int
{
    $groupId = DB::table('amenity_groups')->insertGetId([
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    DB::table('amenity_group_translations')->insert([
        'amenity_group_id' => $groupId, 'locale' => 'en', 'name' => $name,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return $groupId;
}

/** @param  array<string, mixed>  $overrides */
function acrAmenity(int $groupId, string $name, array $overrides = []): int
{
    $amenityId = DB::table('amenities')->insertGetId(array_merge([
        'amenity_group_id' => $groupId, 'is_filterable' => false, 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ], $overrides));

    DB::table('amenity_translations')->insert([
        'amenity_id' => $amenityId, 'locale' => 'en', 'name' => $name,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return $amenityId;
}

/** @param  list<ApiResource>  $resources */
function acrToken(array $resources, array $categoryIds = []): string
{
    $actor = User::factory()->create();
    $client = ApiClient::create(['name' => 'Registry Test Client', 'is_active' => true, 'created_by' => $actor->id]);

    $newAccessToken = app(ApiTokenService::class)->issue(
        $client, 'registry-test', $resources, [], $categoryIds, null, null, $actor,
    );

    return $newAccessToken->plainTextToken;
}

// --- Object Types -----------------------------------------------------

it('rejects a token whose scope does not carry the object_types ability', function (): void {
    acrEnableApiModule();
    acrPrimaryLanguage();
    $token = acrToken([ApiResource::Amenities]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/object-types')
        ->assertForbidden();
});

it('lists only active object types, ordered by id, in the resource shape the controller promises', function (): void {
    acrEnableApiModule();
    acrPrimaryLanguage();

    $parentId = acrObjectType('accommodation', 'Accommodation', ['has_rooms' => true, 'has_availability_status' => true]);
    $childId = acrObjectType('hotel', 'Hotel', ['parent_id' => $parentId]);
    $inactiveId = acrObjectType('inactive-type', 'Inactive Type', ['is_active' => false]);

    $token = acrToken([ApiResource::ObjectTypes]);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/object-types')
        ->assertOk();

    $ids = $response->json('data.*.id');

    expect($ids)->toBe([$parentId, $childId])
        ->and($ids)->not->toContain($inactiveId);

    $response->assertJsonPath('data.0', [
        'id' => $parentId,
        'parent_id' => null,
        'name' => 'Accommodation',
        'has_rooms' => true,
        'has_availability_status' => true,
    ]);

    $response->assertJsonPath('data.1', [
        'id' => $childId,
        'parent_id' => $parentId,
        'name' => 'Hotel',
        'has_rooms' => false,
        'has_availability_status' => false,
    ]);
});

it('narrows the object type list to the category ids in the token scope, unlike the unrestricted default', function (): void {
    acrEnableApiModule();
    acrPrimaryLanguage();

    $inScopeId = acrObjectType('restaurant', 'Restaurant');
    $outOfScopeId = acrObjectType('museum', 'Museum');

    $scopedToken = acrToken([ApiResource::ObjectTypes], categoryIds: [$inScopeId]);

    $scopedIds = $this->withHeader('Authorization', "Bearer {$scopedToken}")
        ->getJson('/api/v1/object-types')
        ->assertOk()
        ->json('data.*.id');

    expect($scopedIds)->toBe([$inScopeId])
        ->and($scopedIds)->not->toContain($outOfScopeId);

    $unrestrictedToken = acrToken([ApiResource::ObjectTypes]);

    $unrestrictedIds = $this->withHeader('Authorization', "Bearer {$unrestrictedToken}")
        ->getJson('/api/v1/object-types')
        ->assertOk()
        ->json('data.*.id');

    expect($unrestrictedIds)->toBe([$inScopeId, $outOfScopeId]);
});

// --- Amenities ----------------------------------------------------------

it('rejects a token whose scope does not carry the amenities ability', function (): void {
    acrEnableApiModule();
    acrPrimaryLanguage();
    $token = acrToken([ApiResource::ObjectTypes]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/amenities')
        ->assertForbidden();
});

it('lists only active amenities, ordered by id, with their group name and filterable flag', function (): void {
    acrEnableApiModule();
    acrPrimaryLanguage();

    $groupId = acrAmenityGroup('General');
    $poolId = acrAmenity($groupId, 'Pool', ['is_filterable' => true]);
    $conciergeId = acrAmenity($groupId, 'Concierge', ['is_filterable' => false]);
    $inactiveId = acrAmenity($groupId, 'Retired Amenity', ['is_active' => false]);

    $token = acrToken([ApiResource::Amenities]);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/amenities')
        ->assertOk();

    $ids = $response->json('data.*.id');

    expect($ids)->toBe([$poolId, $conciergeId])
        ->and($ids)->not->toContain($inactiveId);

    $response->assertJsonPath('data.0', [
        'id' => $poolId,
        'name' => 'Pool',
        'group' => 'General',
        'is_filterable' => true,
    ]);

    $response->assertJsonPath('data.1', [
        'id' => $conciergeId,
        'name' => 'Concierge',
        'group' => 'General',
        'is_filterable' => false,
    ]);
});

it('never narrows the amenity list by the token category scope — the registry belongs to no category', function (): void {
    acrEnableApiModule();
    acrPrimaryLanguage();

    $groupId = acrAmenityGroup('Grounds');
    $parkingId = acrAmenity($groupId, 'Parking');

    // Scoped to a category the amenity has no relationship to at all — an
    // amenity row carries no category column for any narrowing to attach
    // to, so a category-scoped token must still see the full active list.
    $unrelatedTypeId = acrObjectType('unrelated-type', 'Unrelated Type');
    $token = acrToken([ApiResource::Amenities], categoryIds: [$unrelatedTypeId]);

    $ids = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/amenities')
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toBe([$parkingId]);
});
