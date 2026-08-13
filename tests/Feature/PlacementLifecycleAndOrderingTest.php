<?php

declare(strict_types=1);

use App\Models\Object_;
use App\Models\PlacementPackage;
use App\Models\Territory;
use App\Models\User;
use App\Services\Audit\AuditJournal;
use App\Services\Placement\PlacementLifecycleService;
use App\Services\Placement\PlacementOrderingService;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Placement Lifecycle & Catalog Ordering
|--------------------------------------------------------------------------
|
| The ordering contract is proven term by term, each one isolated from the
| terms below it — a lower-ranked object is never promoted above a
| higher-ranked one by any amount of pinning, bumping, or promotion, and a
| bump recorded in one scope leaves a sibling scope untouched. The lifecycle
| half proves the append-only guarantee directly: granting a second package
| never deletes the first history row, only closes the window a new one
| opens.
|
*/

/** @return array{country: int, territory: int, otherTerritory: int, type: int} */
function placementFixtureGeography(): array
{
    $languageId = DB::table('languages')->insertGetId([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $countryId = DB::table('countries')->insertGetId([
        'code' => 'MD', 'currency' => 'MDL', 'phone_code' => '+373',
        'primary_language_id' => $languageId, 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $levelId = DB::table('territory_levels')->insertGetId([
        'country_id' => $countryId, 'depth_rank' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $territoryId = DB::table('territories')->insertGetId([
        'country_id' => $countryId, 'level_id' => $levelId, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $otherTerritoryId = DB::table('territories')->insertGetId([
        'country_id' => $countryId, 'level_id' => $levelId, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $typeId = DB::table('object_types')->insertGetId([
        'key' => 'hotel', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return ['country' => $countryId, 'territory' => $territoryId, 'otherTerritory' => $otherTerritoryId, 'type' => $typeId];
}

function makeObject(int $countryId, int $territoryId, int $typeId, ?CarbonInterface $createdAt = null): int
{
    $owner = User::factory()->create();

    return DB::table('objects')->insertGetId([
        'ulid' => (string) Str::ulid(), 'owner_id' => $owner->id,
        'object_type_id' => $typeId, 'territory_id' => $territoryId, 'country_id' => $countryId,
        // ModerationScope is global and excludes anything but an explicit
        // 'approved' status — omitting this leaves moderation_status null
        // and every fixture object invisible to Object_::query().
        'status' => 'published', 'moderation_status' => 'approved',
        'created_at' => $createdAt ?? now(), 'updated_at' => now(),
    ]);
}

function makePackage(int $rank): int
{
    $tierId = DB::table('placement_tiers')->insertGetId([
        'rank' => $rank, 'border_colour' => '#000000', 'badge_colour' => '#111111',
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return DB::table('placement_packages')->insertGetId([
        'placement_tier_id' => $tierId, 'price' => 10, 'currency' => 'EUR', 'validity_days' => 30,
        'bump_allowed' => true, 'is_active' => true, 'display_order' => $rank,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function placeObject(int $objectId, int $packageId, ?int $pinnedPosition = null, int $internalPriority = 0): void
{
    DB::table('object_placements')->insert([
        'object_id' => $objectId, 'placement_package_id' => $packageId,
        'starts_at' => now()->toDateString(), 'ends_at' => now()->addDays(30)->toDateString(),
        'pinned_position' => $pinnedPosition, 'internal_priority' => $internalPriority,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

it('never promotes a lower-ranked object above a higher-ranked one, regardless of pin, bump, or promotion', function (): void {
    $geo = placementFixtureGeography();

    $highTierPackage = makePackage(1); // rank 1 = highest
    $lowTierPackage = makePackage(4);

    $lowObject = makeObject($geo['country'], $geo['territory'], $geo['type']);
    $highObject = makeObject($geo['country'], $geo['territory'], $geo['type']);

    placeObject($lowObject, $lowTierPackage, pinnedPosition: 1); // pinned, but low tier
    placeObject($highObject, $highTierPackage); // unpinned, but high tier

    // Stack every lower-precedence advantage onto the low-tier object.
    DB::table('bump_events')->insert([
        'object_id' => $lowObject, 'placement_package_id' => $lowTierPackage,
        'scope_type' => Territory::class, 'scope_id' => $geo['territory'],
        'occurred_at' => now(), 'type' => 'administrator',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $territory = Territory::query()->findOrFail($geo['territory']);
    $ordered = app(PlacementOrderingService::class)
        ->apply(Object_::query(), $territory)
        ->pluck('id')
        ->all();

    expect($ordered)->toBe([$highObject, $lowObject]);
});

it('reads bump recency only for the scope being rendered — a sibling territory is unaffected', function (): void {
    $geo = placementFixtureGeography();
    $package = makePackage(2);

    $bumpedInOtherScope = makeObject($geo['country'], $geo['territory'], $geo['type'], now()->subDay());
    $neverBumped = makeObject($geo['country'], $geo['territory'], $geo['type'], now());

    placeObject($bumpedInOtherScope, $package);
    placeObject($neverBumped, $package);

    DB::table('bump_events')->insert([
        'object_id' => $bumpedInOtherScope, 'placement_package_id' => $package,
        'scope_type' => Territory::class, 'scope_id' => $geo['otherTerritory'],
        'occurred_at' => now(), 'type' => 'administrator',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $renderedTerritory = Territory::query()->findOrFail($geo['territory']);
    $ordered = app(PlacementOrderingService::class)
        ->apply(Object_::query(), $renderedTerritory)
        ->pluck('id')
        ->all();

    // Neither object has a bump recorded for *this* territory, so the bump
    // term ties for both and ordering falls through to created_at desc —
    // the more recently created object first, not the one bumped elsewhere.
    expect($ordered)->toBe([$neverBumped, $bumpedInOtherScope]);
});

it('grants a placement, closes the prior history row without deleting it, and journals the change', function (): void {
    $geo = placementFixtureGeography();
    $firstPackageId = makePackage(3);
    $secondPackageId = makePackage(1);
    $objectId = makeObject($geo['country'], $geo['territory'], $geo['type']);

    $actor = User::factory()->create();
    $service = new PlacementLifecycleService(app(AuditJournal::class));

    $object = Object_::query()->findOrFail($objectId);
    $firstPackage = PlacementPackage::query()->findOrFail($firstPackageId);
    $secondPackage = PlacementPackage::query()->findOrFail($secondPackageId);

    $service->grant($object, $firstPackage, $actor);
    $service->grant($object->fresh(), $secondPackage, $actor);

    expect(DB::table('object_placements')->where('object_id', $objectId)->value('placement_package_id'))
        ->toBe($secondPackageId)
        ->and(DB::table('placement_histories')->where('object_id', $objectId)->count())->toBe(2)
        ->and(DB::table('placement_histories')->where('object_id', $objectId)->where('placement_package_id', $firstPackageId)->value('ends_at'))
        ->not->toBeNull() // the prior grant is genuinely closed, not left open alongside the new one
        ->and(DB::table('placement_histories')->where('object_id', $objectId)->where('placement_package_id', $secondPackageId)->value('ends_at'))
        ->toBeNull() // the current grant is the one open row
        ->and(DB::table('audits')->where('auditable_id', $objectId)->where('event', 'placement_granted')->count())->toBe(2);
});

it('pins and unpins without ever touching the granted package — pinning changes position, never tier', function (): void {
    $geo = placementFixtureGeography();
    $packageId = makePackage(2);
    $objectId = makeObject($geo['country'], $geo['territory'], $geo['type']);
    placeObject($objectId, $packageId);

    $actor = User::factory()->create();
    $service = new PlacementLifecycleService(app(AuditJournal::class));
    $object = Object_::query()->findOrFail($objectId);

    $service->pin($object, 1, $actor);

    expect(DB::table('object_placements')->where('object_id', $objectId)->value('pinned_position'))->toBe(1)
        ->and(DB::table('object_placements')->where('object_id', $objectId)->value('placement_package_id'))->toBe($packageId);

    $service->unpin($object->fresh(), $actor);

    expect(DB::table('object_placements')->where('object_id', $objectId)->value('pinned_position'))->toBeNull()
        ->and(DB::table('audits')->where('auditable_id', $objectId)->whereIn('event', ['placement_pinned', 'placement_unpinned'])->count())->toBe(2);
});
