<?php

declare(strict_types=1);

use App\Livewire\Public\CatalogSearch;
use App\Models\Object_;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Placement-Tier Ordering Invariant — Every Public Listing Surface
|--------------------------------------------------------------------------
|
| PlacementOrderingService::apply() is the one tier-ordering contract this
| phase's every listing surface calls through CatalogQueryService — never a
| page-local ORDER BY. Each surface's own task already falsifies this in
| isolation; this sweeps every one of them in one run so no surface can
| silently regress to its own reimplementation without this file catching
| it too.
|
| The catalog map is deliberately excluded: pins plot at real coordinates,
| not a stacked list, and CatalogQueryService::pins() intentionally skips
| PlacementOrderingService entirely (T-5A06's own documented decision) — a
| tier-ordering assertion would contradict working, correct behaviour, not
| verify it.
|
| Tagged `slow`: touches six separate public surfaces in one run, excluded
| from `composer test`/`quality`, run explicitly.
|
*/

/** @return array{languageId: int, countryId: int} */
function tierInvariantCountry(): array
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

    return compact('languageId', 'countryId');
}

function tierInvariantMakeTerritory(int $countryId, string $name): int
{
    $levelId = DB::table('territory_levels')->insertGetId([
        'country_id' => $countryId, 'depth_rank' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $territoryId = DB::table('territories')->insertGetId([
        'country_id' => $countryId, 'level_id' => $levelId, 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('territory_translations')->insert([
        'territory_id' => $territoryId, 'locale' => 'en', 'name' => $name, 'slug' => Str::slug($name),
        'needs_review' => false, 'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);

    return $territoryId;
}

function tierInvariantMakeType(string $key, bool $isActive = true): int
{
    $typeId = DB::table('object_types')->insertGetId([
        'key' => $key, 'is_active' => $isActive, 'has_rooms' => false, 'has_availability_status' => false,
        'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('object_type_translations')->insert([
        'object_type_id' => $typeId, 'locale' => 'en', 'name' => ucfirst($key),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return $typeId;
}

/** @param  array<string, mixed>  $overrides */
function tierInvariantMakeObject(int $countryId, int $territoryId, int $typeId, string $name, array $overrides = []): Object_
{
    $objectId = DB::table('objects')->insertGetId(array_merge([
        'ulid' => (string) Str::ulid(), 'owner_id' => User::factory()->create()->id,
        'object_type_id' => $typeId, 'territory_id' => $territoryId, 'country_id' => $countryId,
        'status' => 'published', 'moderation_status' => 'approved', 'availability_status' => 'available',
        'created_at' => now(), 'updated_at' => now(),
    ], $overrides));

    DB::table('object_translations')->insert([
        'object_id' => $objectId, 'locale' => 'en', 'name' => $name,
        'slug' => Str::slug($name).'-'.$objectId, 'needs_review' => false, 'published_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    /** @var Object_ $object */
    $object = Object_::query()->findOrFail($objectId);

    return $object;
}

function tierInvariantGivePlacement(Object_ $object, string $badgeText = 'VIP'): void
{
    static $rank = 0;
    $rank++;

    $tierId = DB::table('placement_tiers')->insertGetId([
        'rank' => $rank, 'border_colour' => '#f8bb44', 'badge_colour' => '#f8bb44',
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('placement_tier_translations')->insert([
        'placement_tier_id' => $tierId, 'locale' => 'en', 'label' => $badgeText, 'badge_text' => $badgeText,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $packageId = DB::table('placement_packages')->insertGetId([
        'placement_tier_id' => $tierId, 'price' => 10, 'currency' => 'EUR', 'validity_days' => 30,
        'bump_allowed' => false, 'is_active' => true, 'display_order' => $rank,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('object_placements')->insert([
        'object_id' => $object->id, 'placement_package_id' => $packageId,
        'starts_at' => now()->subDays(1)->toDateString(), 'ends_at' => now()->addDays(30)->toDateString(),
        'internal_priority' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

it('holds tier precedence on the catalog search page', function (): void {
    $fixture = tierInvariantCountry();
    $territoryId = tierInvariantMakeTerritory($fixture['countryId'], 'Catalog Territory');
    $typeId = tierInvariantMakeType('hotel-catalog');
    $standard = tierInvariantMakeObject($fixture['countryId'], $territoryId, $typeId, 'Catalog Standard');
    $vip = tierInvariantMakeObject($fixture['countryId'], $territoryId, $typeId, 'Catalog VIP');
    tierInvariantGivePlacement($vip, 'VIP');

    Livewire::test(CatalogSearch::class)
        ->assertSeeInOrder(['Catalog VIP', 'Catalog Standard']);
})->group('slow');

it("holds tier precedence in a territory page's own catalog block", function (): void {
    $fixture = tierInvariantCountry();
    $territoryId = tierInvariantMakeTerritory($fixture['countryId'], 'Invariant Territory');
    $typeId = tierInvariantMakeType('hotel-territory');
    $standard = tierInvariantMakeObject($fixture['countryId'], $territoryId, $typeId, 'Territory Standard');
    $vip = tierInvariantMakeObject($fixture['countryId'], $territoryId, $typeId, 'Territory VIP');
    tierInvariantGivePlacement($vip, 'VIP');

    $html = (string) $this->get(route('public.territories.show', ['lang' => 'en', 'territory' => $territoryId]))->getContent();

    expect(strpos($html, 'Territory VIP'))->toBeLessThan(strpos($html, 'Territory Standard'));
})->group('slow');

it("holds tier precedence in the home page's own country-scoped object rails", function (): void {
    // Both the recommended and newest-objects rails resolve through the
    // identical CatalogQueryService/PlacementOrderingService call
    // (PublicHomeTest's own task already falsifies this distinctly per
    // rail); this fixture necessarily satisfies both rails' own criteria
    // at once, so the first rendered occurrence of each name — whichever
    // rail composition places first — is what this sweep checks.
    $fixture = tierInvariantCountry();
    $territoryId = tierInvariantMakeTerritory($fixture['countryId'], 'Home Territory');
    $typeId = tierInvariantMakeType('hotel-home');
    $standard = tierInvariantMakeObject($fixture['countryId'], $territoryId, $typeId, 'Home Standard');
    $vip = tierInvariantMakeObject($fixture['countryId'], $territoryId, $typeId, 'Home VIP');
    tierInvariantGivePlacement($vip, 'VIP');

    $this->post(route('public.country-preference', ['lang' => 'en']), ['country' => 'MD']);
    $html = (string) $this->get(route('public.home', ['lang' => 'en']))->getContent();

    expect(strpos($html, 'Home VIP'))->toBeLessThan(strpos($html, 'Home Standard'));
})->group('slow');

it("holds tier precedence in an object profile page's own nearby and similar blocks", function (): void {
    $fixture = tierInvariantCountry();
    $nearbyTerritoryId = tierInvariantMakeTerritory($fixture['countryId'], 'Nearby Territory');
    $elsewhereTerritoryId = tierInvariantMakeTerritory($fixture['countryId'], 'Elsewhere Territory');
    $typeId = tierInvariantMakeType('hotel-related');
    $main = tierInvariantMakeObject($fixture['countryId'], $nearbyTerritoryId, $typeId, 'Related Main');

    // Nearby: same territory as $main, different tiers.
    $nearbyStandard = tierInvariantMakeObject($fixture['countryId'], $nearbyTerritoryId, $typeId, 'Nearby Standard');
    $nearbyVip = tierInvariantMakeObject($fixture['countryId'], $nearbyTerritoryId, $typeId, 'Nearby VIP');
    tierInvariantGivePlacement($nearbyVip, 'VIP');

    // Similar: same type, same country, a different territory — proven
    // distinct from "nearby" in T-5B04's own test; here only tier order
    // within it matters.
    $similarStandard = tierInvariantMakeObject($fixture['countryId'], $elsewhereTerritoryId, $typeId, 'Similar Standard');
    $similarVip = tierInvariantMakeObject($fixture['countryId'], $elsewhereTerritoryId, $typeId, 'Similar VIP');
    tierInvariantGivePlacement($similarVip, 'VIP');

    $html = (string) $this->get(route('public.objects.show', ['lang' => 'en', 'object' => $main]))->getContent();

    $nearbyHeadingStart = strpos($html, __('public.object.nearby_heading'));
    $similarHeadingStart = strpos($html, __('public.object.similar_heading'));
    expect($nearbyHeadingStart)->not->toBeFalse()->and($similarHeadingStart)->not->toBeFalse();

    $nearbyBlock = substr($html, $nearbyHeadingStart, $similarHeadingStart - $nearbyHeadingStart);
    $similarBlock = substr($html, $similarHeadingStart);

    expect(strpos($nearbyBlock, 'Nearby VIP'))->toBeLessThan(strpos($nearbyBlock, 'Nearby Standard'))
        ->and(strpos($similarBlock, 'Similar VIP'))->toBeLessThan(strpos($similarBlock, 'Similar Standard'));
})->group('slow');
