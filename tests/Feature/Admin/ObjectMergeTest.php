<?php

declare(strict_types=1);

use App\Exceptions\ObjectMergeRefusedException;
use App\Models\Object_;
use App\Models\Redirect;
use App\Models\User;
use App\Services\Objects\ObjectMergeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use OwenIt\Auditing\Models\Audit;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Administrator-Confirmed Object Merge
|--------------------------------------------------------------------------
|
| Duplicate detection during import only ever presents a pairing — it never
| writes anything. By the time two catalog entries genuinely need combining,
| both already exist as ordinary, persisted objects, and an administrator
| decides which one survives. Everything downstream of the merged-away
| record moves to the survivor inside one transaction; the merged-away
| record itself is archived, and its own public URL permanently redirects
| to the survivor's.
|
*/

// The public-shell cache is keyed partly by database id, and RefreshDatabase
// resets auto-increment between test cases.
beforeEach(fn () => Cache::flush());

/** @return array{countryId: int, territoryId: int, typeId: int} */
function objectMergeRegistry(): array
{
    $languageId = DB::table('languages')->insertGetId([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $countryId = DB::table('countries')->insertGetId([
        'code' => 'MD', 'currency' => 'MDL', 'phone_code' => '+373',
        'primary_language_id' => $languageId, 'is_active' => true, 'display_order' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $levelId = DB::table('territory_levels')->insertGetId([
        'country_id' => $countryId, 'depth_rank' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $territoryId = DB::table('territories')->insertGetId([
        'country_id' => $countryId, 'level_id' => $levelId, 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $typeId = DB::table('object_types')->insertGetId([
        'key' => 'hotel', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return compact('countryId', 'territoryId', 'typeId');
}

/**
 * @param  array{countryId: int, territoryId: int, typeId: int}  $scope
 * @param  array<string, mixed>  $overrides
 */
function objectMergeMakeObject(array $scope, string $name, array $overrides = []): Object_
{
    $objectId = DB::table('objects')->insertGetId(array_merge([
        'ulid' => (string) Str::ulid(),
        'owner_id' => User::factory()->create()->id,
        'object_type_id' => $scope['typeId'],
        'territory_id' => $scope['territoryId'],
        'country_id' => $scope['countryId'],
        'status' => 'published',
        'moderation_status' => 'approved',
        'created_at' => now(), 'updated_at' => now(),
    ], $overrides));

    DB::table('object_translations')->insert([
        'object_id' => $objectId, 'locale' => 'en', 'name' => $name,
        'slug' => Str::slug($name), 'needs_review' => false, 'published_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    /** @var Object_ $object */
    $object = Object_::query()->findOrFail($objectId);

    return $object;
}

function objectMergeAttachMedia(int $objectId): int
{
    return DB::table('media')->insertGetId([
        'model_type' => Object_::class,
        'model_id' => $objectId,
        'uuid' => (string) Str::uuid(),
        'collection_name' => 'photos',
        'name' => 'cover',
        'file_name' => 'cover.jpg',
        'mime_type' => 'image/jpeg',
        'disk' => 'public',
        'size' => 1024,
        'manipulations' => json_encode([], JSON_THROW_ON_ERROR),
        'custom_properties' => json_encode([], JSON_THROW_ON_ERROR),
        'generated_conversions' => json_encode([], JSON_THROW_ON_ERROR),
        'responsive_images' => json_encode([], JSON_THROW_ON_ERROR),
        'order_column' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function objectMergeAttachPlacement(int $objectId, int $rank): int
{
    $tierId = DB::table('placement_tiers')->insertGetId([
        'rank' => $rank, 'border_colour' => '#000000', 'badge_colour' => '#ffffff', 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $packageId = DB::table('placement_packages')->insertGetId([
        'placement_tier_id' => $tierId, 'price' => 10, 'currency' => 'USD', 'validity_days' => 30,
        'bump_allowed' => false, 'is_active' => true, 'display_order' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return DB::table('object_placements')->insertGetId([
        'object_id' => $objectId, 'placement_package_id' => $packageId,
        'starts_at' => now()->toDateString(), 'ends_at' => now()->addDays(30)->toDateString(),
        'internal_priority' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function objectMergeAttachStatDaily(int $objectId, string $date, int $count): int
{
    return DB::table('stat_dailies')->insertGetId([
        'date' => $date, 'subject_type' => Object_::class, 'subject_id' => $objectId,
        'kind' => 'object_page_view', 'count' => $count,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function objectMergeActor(): User
{
    Permission::findOrCreate('admin_panel_access', 'web');
    Permission::findOrCreate('object.delete', 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $role = Role::findOrCreate('object_merge_test_admin', 'web');
    $role->syncPermissions(['admin_panel_access', 'object.delete']);

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

it('reattaches media, placement, and statistics to the survivor', function (): void {
    $scope = objectMergeRegistry();
    $survivor = objectMergeMakeObject($scope, 'Hotel Astoria');
    $mergedAway = objectMergeMakeObject($scope, 'Astoria Hotel');
    $actor = objectMergeActor();

    $mediaId = objectMergeAttachMedia($mergedAway->id);
    $placementId = objectMergeAttachPlacement($mergedAway->id, 1);

    // A stat_dailies row that will collide with the survivor's own —
    // proves the unique-rollup fold path, not just the plain repoint.
    objectMergeAttachStatDaily($survivor->id, '2026-01-01', 5);
    objectMergeAttachStatDaily($mergedAway->id, '2026-01-01', 3);
    // A stat_dailies row with nothing to collide against — proves the
    // plain repoint path.
    objectMergeAttachStatDaily($mergedAway->id, '2026-01-02', 7);

    app(ObjectMergeService::class)->merge($survivor, $mergedAway, $actor);

    expect(DB::table('media')->where('id', $mediaId)->value('model_id'))->toBe($survivor->id)
        ->and(DB::table('object_placements')->where('id', $placementId)->value('object_id'))->toBe($survivor->id)
        ->and(DB::table('stat_dailies')->where('subject_id', $survivor->id)->where('date', '2026-01-01')->value('count'))->toBe(8)
        ->and(DB::table('stat_dailies')->where('subject_id', $mergedAway->id)->where('date', '2026-01-01')->exists())->toBeFalse()
        ->and(DB::table('stat_dailies')->where('subject_id', $survivor->id)->where('date', '2026-01-02')->value('count'))->toBe(7)
        ->and($mergedAway->fresh()->trashed())->toBeTrue();
});

it('leaves the survivor\'s own placement untouched when both records already carry one', function (): void {
    $scope = objectMergeRegistry();
    $survivor = objectMergeMakeObject($scope, 'Hotel Astoria');
    $mergedAway = objectMergeMakeObject($scope, 'Astoria Hotel');
    $actor = objectMergeActor();

    $survivorPlacementId = objectMergeAttachPlacement($survivor->id, 1);
    $mergedAwayPlacementId = objectMergeAttachPlacement($mergedAway->id, 2);

    app(ObjectMergeService::class)->merge($survivor, $mergedAway, $actor);

    expect(DB::table('object_placements')->where('id', $survivorPlacementId)->value('object_id'))->toBe($survivor->id)
        ->and(DB::table('object_placements')->where('id', $mergedAwayPlacementId)->value('object_id'))->toBe($mergedAway->id);
});

it('leaves a permanent redirect at the merged-away object\'s own URL, serving a 301 to the survivor', function (): void {
    $scope = objectMergeRegistry();
    $survivor = objectMergeMakeObject($scope, 'Hotel Astoria');
    $mergedAway = objectMergeMakeObject($scope, 'Astoria Hotel');
    $actor = objectMergeActor();

    app(ObjectMergeService::class)->merge($survivor, $mergedAway, $actor);

    $redirect = Redirect::query()->where('locale', 'en')->where('from_path', 'o/astoria-hotel')->first();

    expect($redirect)->not->toBeNull()
        ->and($redirect->to_path)->toBe('o/hotel-astoria')
        ->and($redirect->is_active)->toBeTrue();

    $response = $this->get('/en/o/astoria-hotel');
    $response->assertStatus(301);
    $response->assertRedirect('/en/o/hotel-astoria');
});

it('writes the redirect and every other change inside the merge\'s own transaction — a failure partway through leaves nothing', function (): void {
    $scope = objectMergeRegistry();
    $survivor = objectMergeMakeObject($scope, 'Hotel Astoria');
    $mergedAway = objectMergeMakeObject($scope, 'Astoria Hotel');
    $actor = objectMergeActor();

    $mediaId = objectMergeAttachMedia($mergedAway->id);
    $placementId = objectMergeAttachPlacement($mergedAway->id, 1);

    // Injects a genuine failure into the real journal write's own insert
    // step — not a re-implementation of the service's transaction elsewhere
    // — by throwing from inside the query listener the moment the audit
    // entry actually gets written. That write is the *last* step inside
    // `ObjectMergeService::merge()`'s transaction, so this exercises a
    // failure after the redirect, the media reattachment, and the soft
    // delete have all already run, proving they only survive if the whole
    // transaction commits.
    DB::listen(function ($query): void {
        if (str_contains($query->sql, 'insert into "audits"')) {
            throw new RuntimeException('forced failure for the rollback test');
        }
    });

    expect(fn () => app(ObjectMergeService::class)->merge($survivor, $mergedAway, $actor))
        ->toThrow(RuntimeException::class, 'forced failure for the rollback test');

    DB::flushQueryLog();

    expect(Redirect::query()->count())->toBe(0)
        ->and($mergedAway->fresh()->trashed())->toBeFalse()
        ->and(DB::table('media')->where('id', $mediaId)->value('model_id'))->toBe($mergedAway->id)
        ->and(DB::table('object_placements')->where('id', $placementId)->value('object_id'))->toBe($mergedAway->id);
});

it('journals the merge naming both the survivor and the merged-away record', function (): void {
    $scope = objectMergeRegistry();
    $survivor = objectMergeMakeObject($scope, 'Hotel Astoria');
    $mergedAway = objectMergeMakeObject($scope, 'Astoria Hotel');
    $actor = objectMergeActor();

    app(ObjectMergeService::class)->merge($survivor, $mergedAway, $actor);

    $entry = Audit::query()->where('event', 'object_merged')->latest('id')->first();

    expect($entry)->not->toBeNull()
        ->and($entry->auditable_type)->toBe(Object_::class)
        ->and($entry->auditable_id)->toBe($survivor->id)
        ->and($entry->old_values['merged_away']['id'])->toBe($mergedAway->id)
        ->and($entry->old_values['merged_away']['name'])->toBe('Astoria Hotel')
        ->and($entry->new_values['survivor']['id'])->toBe($survivor->id)
        ->and($entry->new_values['survivor']['name'])->toBe('Hotel Astoria')
        ->and($entry->user_id)->toBe($actor->id);
});

it('refuses to merge an object with itself', function (): void {
    $scope = objectMergeRegistry();
    $object = objectMergeMakeObject($scope, 'Hotel Astoria');
    $actor = objectMergeActor();

    app(ObjectMergeService::class)->merge($object, $object, $actor);
})->throws(ObjectMergeRefusedException::class);

it('denies the merge ability to a staff account without object.delete', function (): void {
    $scope = objectMergeRegistry();
    $survivor = objectMergeMakeObject($scope, 'Hotel Astoria');
    $mergedAway = objectMergeMakeObject($scope, 'Astoria Hotel');

    Permission::findOrCreate('admin_panel_access', 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $limitedUser = User::factory()->create();
    $limitedUser->givePermissionTo('admin_panel_access');

    expect(Gate::forUser($limitedUser)->allows('merge', $survivor))->toBeFalse()
        ->and(Gate::forUser($limitedUser)->allows('merge', $mergedAway))->toBeFalse();
});
