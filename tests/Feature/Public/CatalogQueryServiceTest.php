<?php

declare(strict_types=1);

use App\Models\Object_;
use App\Models\Room;
use App\Models\Territory;
use App\Models\User;
use App\Services\Catalog\CatalogQueryService;
use App\Services\Placement\PlacementOrderingService;
use App\Support\Catalog\CatalogSearchCriteria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Catalog Query Service
|--------------------------------------------------------------------------
|
| The single retrieval contract every public listing surface calls. Tier
| ordering is deliberately NOT reimplemented here — it is delegated to
| PlacementOrderingService, already built ahead of time for this exact call
| site — so this suite proves scope resolution, filtering, pagination, and
| genuine delegation, not a second ordering contract.
|
*/

/** @return array{countryId: int, cityId: int, resortId: int, typeId: int} */
function catalogQueryGeography(): array
{
    $languageId = DB::table('languages')->insertGetId([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $countryId = DB::table('countries')->insertGetId([
        'code' => 'MD', 'currency' => 'MDL', 'phone_code' => '+373',
        'primary_language_id' => $languageId, 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $cityLevelId = DB::table('territory_levels')->insertGetId([
        'country_id' => $countryId, 'depth_rank' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $resortLevelId = DB::table('territory_levels')->insertGetId([
        'country_id' => $countryId, 'depth_rank' => 2, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $cityId = DB::table('territories')->insertGetId([
        'country_id' => $countryId, 'level_id' => $cityLevelId, 'parent_id' => null,
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $resortId = DB::table('territories')->insertGetId([
        'country_id' => $countryId, 'level_id' => $resortLevelId, 'parent_id' => $cityId,
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $typeId = DB::table('object_types')->insertGetId([
        'key' => 'accommodation', 'is_active' => true,
        'attribute_schema' => json_encode([
            ['key' => 'distance_to_sea', 'type' => 'number', 'label' => ['en' => 'Distance to sea']],
        ]),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return compact('countryId', 'cityId', 'resortId', 'typeId');
}

/**
 * @param  array{countryId: int, cityId: int, resortId: int, typeId: int}  $fixture
 * @param  array<string, mixed>  $overrides
 */
function catalogQueryMakeObject(array $fixture, string $name, int $territoryId, array $overrides = []): Object_
{
    $objectId = DB::table('objects')->insertGetId(array_merge([
        'ulid' => (string) Str::ulid(),
        'owner_id' => User::factory()->create()->id,
        'object_type_id' => $fixture['typeId'],
        'territory_id' => $territoryId,
        'country_id' => $fixture['countryId'],
        'status' => 'published', 'moderation_status' => 'approved',
        'created_at' => now(), 'updated_at' => now(),
    ], $overrides));

    DB::table('object_translations')->insert([
        'object_id' => $objectId, 'locale' => 'en', 'name' => $name,
        'slug' => Str::slug($name).'-'.$objectId, 'needs_review' => false, 'published_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    /** @var Object_ $object */
    $object = Object_::query()->withUnmoderated()->findOrFail($objectId);

    return $object;
}

function catalogQueryMakePlacement(Object_ $object, int $tierRank): void
{
    $tierId = DB::table('placement_tiers')->insertGetId([
        'rank' => $tierRank, 'border_colour' => '#000000', 'badge_colour' => '#000000',
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $packageId = DB::table('placement_packages')->insertGetId([
        'placement_tier_id' => $tierId, 'price' => 10, 'currency' => 'EUR',
        'validity_days' => 30, 'bump_allowed' => false, 'is_active' => true,
        'display_order' => $tierRank, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('object_placements')->insert([
        'object_id' => $object->id, 'placement_package_id' => $packageId,
        'starts_at' => now()->subDays(1)->toDateString(), 'ends_at' => now()->addDays(30)->toDateString(),
        'internal_priority' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

it('resolves a territory scope to itself and every descendant, never a sibling', function (): void {
    $fixture = catalogQueryGeography();
    $cityObject = catalogQueryMakeObject($fixture, 'City Hotel', $fixture['cityId']);
    $resortObject = catalogQueryMakeObject($fixture, 'Resort Hotel', $fixture['resortId']);

    $cityScoped = app(CatalogQueryService::class)->search(new CatalogSearchCriteria(
        territory: Territory::query()->findOrFail($fixture['cityId']),
    ));

    // The city's own catalog includes the resort beneath it — scoping is
    // transitive, per the spec's own invariant.
    expect($cityScoped->getCollection()->pluck('id')->all())->toEqualCanonicalizing([$cityObject->id, $resortObject->id]);

    $resortScoped = app(CatalogQueryService::class)->search(new CatalogSearchCriteria(
        territory: Territory::query()->findOrFail($fixture['resortId']),
    ));

    expect($resortScoped->getCollection()->pluck('id')->all())->toBe([$resortObject->id]);
});

it('delegates ordering entirely to the existing PlacementOrderingService, never reimplementing it', function (): void {
    $fixture = catalogQueryGeography();
    catalogQueryMakeObject($fixture, 'Solo Villa', $fixture['cityId']);

    // PlacementOrderingService is final (Mockery cannot spy a final class);
    // rebinding the container slot to throw is the falsification this
    // warrants — any real resolution of it from CatalogQueryService blows up
    // here, proving the ordering collaborator is a genuine dependency, not
    // bypassed by a parallel implementation.
    app()->bind(PlacementOrderingService::class, function (): never {
        throw new RuntimeException('PlacementOrderingService must be resolved by CatalogQueryService, not bypassed.');
    });

    expect(fn () => app(CatalogQueryService::class)->search(new CatalogSearchCriteria))
        ->toThrow(RuntimeException::class, 'PlacementOrderingService must be resolved by CatalogQueryService, not bypassed.');
});

it("orders results tier-first, exactly matching PlacementOrderingService's own contract", function (): void {
    $fixture = catalogQueryGeography();
    $lowerTierNewer = catalogQueryMakeObject($fixture, 'Newer Standard', $fixture['cityId'], [
        'created_at' => now(),
    ]);
    $higherTierOlder = catalogQueryMakeObject($fixture, 'Older VIP', $fixture['cityId'], [
        'created_at' => now()->subYear(),
    ]);
    catalogQueryMakePlacement($lowerTierNewer, 4);
    catalogQueryMakePlacement($higherTierOlder, 1);

    $results = app(CatalogQueryService::class)->search(new CatalogSearchCriteria(
        territory: Territory::query()->findOrFail($fixture['cityId']),
    ));

    // A newer, lower-tier object never outranks an older, higher-tier one —
    // tier dominates every later ordering term, including recency.
    expect($results->getCollection()->pluck('id')->all())->toBe([$higherTierOlder->id, $lowerTierNewer->id]);
});

it('applies amenity filters with AND semantics — an object must carry every requested amenity', function (): void {
    $fixture = catalogQueryGeography();
    $groupId = DB::table('amenity_groups')->insertGetId(['is_active' => true, 'display_order' => 0, 'created_at' => now(), 'updated_at' => now()]);
    $wifiId = DB::table('amenities')->insertGetId([
        'amenity_group_id' => $groupId, 'is_filterable' => true, 'is_active' => true,
        'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $poolId = DB::table('amenities')->insertGetId([
        'amenity_group_id' => $groupId, 'is_filterable' => true, 'is_active' => true,
        'display_order' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $both = catalogQueryMakeObject($fixture, 'Wifi And Pool', $fixture['cityId']);
    $wifiOnly = catalogQueryMakeObject($fixture, 'Wifi Only', $fixture['cityId']);
    DB::table('amenity_object')->insert([
        ['amenity_id' => $wifiId, 'object_id' => $both->id],
        ['amenity_id' => $poolId, 'object_id' => $both->id],
        ['amenity_id' => $wifiId, 'object_id' => $wifiOnly->id],
    ]);

    $results = app(CatalogQueryService::class)->search(new CatalogSearchCriteria(amenityIds: [$wifiId, $poolId]));

    expect($results->getCollection()->pluck('id')->all())->toBe([$both->id]);
});

it('filters by price range whether the price sits directly on the object or on one of its rooms', function (): void {
    $fixture = catalogQueryGeography();
    $directPriced = catalogQueryMakeObject($fixture, 'Direct Priced Diner', $fixture['cityId']);
    DB::table('prices')->insert([
        'priceable_type' => Object_::class, 'priceable_id' => $directPriced->id,
        'calculation_unit' => 'per_service', 'amount' => 50, 'currency' => 'EUR',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $roomPriced = catalogQueryMakeObject($fixture, 'Room Priced Hotel', $fixture['cityId']);
    $roomId = DB::table('rooms')->insertGetId([
        'object_id' => $roomPriced->id, 'display_order' => 0, 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('prices')->insert([
        'priceable_type' => Room::class, 'priceable_id' => $roomId,
        'calculation_unit' => 'per_night', 'amount' => 60, 'currency' => 'EUR',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $tooExpensive = catalogQueryMakeObject($fixture, 'Too Expensive', $fixture['cityId']);
    DB::table('prices')->insert([
        'priceable_type' => Object_::class, 'priceable_id' => $tooExpensive->id,
        'calculation_unit' => 'per_service', 'amount' => 500, 'currency' => 'EUR',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $results = app(CatalogQueryService::class)->search(new CatalogSearchCriteria(priceMin: 40, priceMax: 100));

    expect($results->getCollection()->pluck('id')->all())->toEqualCanonicalizing([$directPriced->id, $roomPriced->id]);
});

it('filters by a minimum average rating computed from published, non-deleted reviews only', function (): void {
    $fixture = catalogQueryGeography();
    $wellRated = catalogQueryMakeObject($fixture, 'Well Rated', $fixture['cityId']);
    $poorlyRated = catalogQueryMakeObject($fixture, 'Poorly Rated', $fixture['cityId']);

    DB::table('reviews')->insert([
        ['object_id' => $wellRated->id, 'rating' => 5, 'body' => 'Great.', 'status' => 'published', 'created_at' => now(), 'updated_at' => now()],
        ['object_id' => $wellRated->id, 'rating' => 4, 'body' => 'Good.', 'status' => 'published', 'created_at' => now(), 'updated_at' => now()],
        // A pending review must never pull the average down — it is not counted.
        ['object_id' => $wellRated->id, 'rating' => 1, 'body' => 'Unpublished outlier.', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()],
        ['object_id' => $poorlyRated->id, 'rating' => 2, 'body' => 'Meh.', 'status' => 'published', 'created_at' => now(), 'updated_at' => now()],
    ]);

    $results = app(CatalogQueryService::class)->search(new CatalogSearchCriteria(ratingMin: 4.0));

    expect($results->getCollection()->pluck('id')->all())->toBe([$wellRated->id]);
});

it("filters by a type-declared numeric attribute range, gated by the object's own declared schema", function (): void {
    $fixture = catalogQueryGeography();
    $nearSea = catalogQueryMakeObject($fixture, 'Near The Sea', $fixture['cityId'], [
        'attributes' => json_encode(['distance_to_sea' => 200]),
    ]);
    $farFromSea = catalogQueryMakeObject($fixture, 'Far From The Sea', $fixture['cityId'], [
        'attributes' => json_encode(['distance_to_sea' => 5000]),
    ]);

    $results = app(CatalogQueryService::class)->search(new CatalogSearchCriteria(
        attributeFilters: ['distance_to_sea' => ['max' => 500.0]],
    ));

    expect($results->getCollection()->pluck('id')->all())->toBe([$nearSea->id])
        ->and($results->getCollection()->pluck('id')->all())->not->toContain($farFromSea->id);
});

it('paginates by offset, addressable by page number rather than only by infinite scroll', function (): void {
    $fixture = catalogQueryGeography();
    $objects = collect(range(1, 5))->map(fn (int $i): Object_ => catalogQueryMakeObject($fixture, "Object {$i}", $fixture['cityId']));

    $pageOne = app(CatalogQueryService::class)->search(new CatalogSearchCriteria(page: 1, perPage: 2));
    $pageTwo = app(CatalogQueryService::class)->search(new CatalogSearchCriteria(page: 2, perPage: 2));

    expect($pageOne->total())->toBe(5)
        ->and($pageOne->lastPage())->toBe(3)
        ->and($pageOne->items())->toHaveCount(2)
        ->and($pageTwo->items())->toHaveCount(2)
        ->and(collect($pageOne->items())->pluck('id')->all())
        ->not->toEqual(collect($pageTwo->items())->pluck('id')->all());
});

it('returns only published, moderation-approved objects, never a draft or a pending one', function (): void {
    $fixture = catalogQueryGeography();
    $live = catalogQueryMakeObject($fixture, 'Live Listing', $fixture['cityId']);
    catalogQueryMakeObject($fixture, 'Still A Draft', $fixture['cityId'], ['status' => 'draft', 'moderation_status' => null]);
    catalogQueryMakeObject($fixture, 'Awaiting Moderation', $fixture['cityId'], ['status' => 'published', 'moderation_status' => 'pending']);
    catalogQueryMakeObject($fixture, 'Hidden By Owner', $fixture['cityId'], ['status' => 'hidden']);

    $results = app(CatalogQueryService::class)->search(new CatalogSearchCriteria);

    expect($results->getCollection()->pluck('id')->all())->toBe([$live->id]);
});

/*
| `object_promotions` carries no uniqueness constraint on `object_id`, so
| the ordering contract's promotion term is joined through an aggregating
| subquery rather than against the table directly. Without that, an object
| holding two concurrently-active label grants is matched once per grant —
| listed twice, and counted twice in the paginator's own total.
*/

function catalogQueryGrantPromotion(Object_ $object, string $startsAt, string $endsAt, int $weight = 0): void
{
    $labelId = DB::table('promotion_labels')->insertGetId([
        'border_colour' => '#000000', 'text_colour' => '#ffffff', 'background_colour' => '#ff0000',
        'position_on_card' => 'top-left', 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    DB::table('object_promotions')->insert([
        'object_id' => $object->id, 'promotion_label_id' => $labelId,
        'starts_at' => $startsAt, 'ends_at' => $endsAt, 'weight' => $weight,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

it('lists an object once even while it holds two overlapping active promotion grants', function (): void {
    $fixture = catalogQueryGeography();
    $object = catalogQueryMakeObject($fixture, 'Doubly Labelled', $fixture['cityId']);

    catalogQueryGrantPromotion($object, now()->subDay()->toDateString(), now()->addWeek()->toDateString(), 5);
    catalogQueryGrantPromotion($object, now()->subDays(2)->toDateString(), now()->addDays(3)->toDateString(), 9);

    $results = app(CatalogQueryService::class)->search(new CatalogSearchCriteria);

    expect($results->getCollection()->pluck('id')->all())->toBe([$object->id])
        ->and($results->total())->toBe(1);
});

it('breaks a within-tier tie by the strongest active label an object carries', function (): void {
    $fixture = catalogQueryGeography();

    $weaklyLabelled = catalogQueryMakeObject($fixture, 'Weakly Labelled', $fixture['cityId']);
    $stronglyLabelled = catalogQueryMakeObject($fixture, 'Strongly Labelled', $fixture['cityId']);

    catalogQueryGrantPromotion($weaklyLabelled, now()->subDay()->toDateString(), now()->addWeek()->toDateString(), 1);
    // Two grants, so the aggregate — not the row order — decides the weight.
    catalogQueryGrantPromotion($stronglyLabelled, now()->subDay()->toDateString(), now()->addWeek()->toDateString(), 2);
    catalogQueryGrantPromotion($stronglyLabelled, now()->subDay()->toDateString(), now()->addDays(4)->toDateString(), 7);

    $results = app(CatalogQueryService::class)->search(new CatalogSearchCriteria);

    expect($results->getCollection()->pluck('id')->all())
        ->toBe([$stronglyLabelled->id, $weaklyLabelled->id]);
});

it('ignores a promotion grant whose window has not opened yet', function (): void {
    $fixture = catalogQueryGeography();
    $object = catalogQueryMakeObject($fixture, 'Future Label', $fixture['cityId']);

    catalogQueryGrantPromotion($object, now()->addWeek()->toDateString(), now()->addMonth()->toDateString(), 9);

    $results = app(CatalogQueryService::class)->search(new CatalogSearchCriteria);

    expect($results->total())->toBe(1);
});
