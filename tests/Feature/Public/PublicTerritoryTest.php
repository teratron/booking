<?php

declare(strict_types=1);

use App\Models\Banner;
use App\Models\Object_;
use App\Models\ObjectType;
use App\Models\Territory;
use App\Models\User;
use App\Services\Seo\PublicUrlGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Public Territory Landing Pages
|--------------------------------------------------------------------------
|
| A territory page composes breadcrumb, hero, description, one catalog
| block per active object type (independently omitted when empty), news,
| promotions, a centred map, an SEO text block, and child navigation.
| Scoping is transitive; tier ordering restarts per territory.
|
*/

/** @return array{languageId: int, countryId: int, hotelTypeId: int, restaurantTypeId: int} */
function publicTerritoryRegistry(): array
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
    $hotelTypeId = DB::table('object_types')->insertGetId([
        'key' => 'hotel', 'is_active' => true, 'has_availability_status' => true, 'display_order' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('object_type_translations')->insert([
        'object_type_id' => $hotelTypeId, 'locale' => 'en', 'name' => 'Hotel', 'slug' => 'hotel',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $restaurantTypeId = DB::table('object_types')->insertGetId([
        'key' => 'restaurant', 'is_active' => true, 'has_availability_status' => false, 'display_order' => 2,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('object_type_translations')->insert([
        'object_type_id' => $restaurantTypeId, 'locale' => 'en', 'name' => 'Restaurant', 'slug' => 'restaurant',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return compact('languageId', 'countryId', 'hotelTypeId', 'restaurantTypeId');
}

/** @param  array<string, string>  $overrides */
function publicTerritoryMake(int $countryId, string $name, ?int $parentId = null, array $overrides = []): Territory
{
    $levelId = DB::table('territory_levels')->insertGetId([
        'country_id' => $countryId, 'depth_rank' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $territoryId = DB::table('territories')->insertGetId([
        'country_id' => $countryId, 'level_id' => $levelId, 'parent_id' => $parentId, 'is_active' => true,
        'latitude' => 47.0, 'longitude' => 28.8,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $slug = Str::slug($name);
    $parentPath = $parentId !== null
        ? DB::table('territory_translations')->where('territory_id', $parentId)->where('locale', 'en')->value('full_slug_path')
        : null;

    DB::table('territory_translations')->insert(array_merge([
        'territory_id' => $territoryId, 'country_id' => $countryId, 'locale' => 'en', 'name' => $name, 'slug' => $slug,
        'full_slug_path' => $parentPath !== null ? "{$parentPath}/{$slug}" : $slug,
        'short_description' => "{$name} is a lovely place to visit.",
        'full_description' => "Everything a visitor needs to know about {$name}.",
        'needs_review' => false, 'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ], $overrides));

    /** @var Territory $territory */
    $territory = Territory::query()->findOrFail($territoryId);

    return $territory;
}

/** @param  array<string, mixed>  $overrides */
function publicTerritoryMakeObject(int $countryId, int $territoryId, int $typeId, string $name, array $overrides = []): Object_
{
    $objectId = DB::table('objects')->insertGetId(array_merge([
        'ulid' => (string) Str::ulid(),
        'owner_id' => User::factory()->create()->id,
        'object_type_id' => $typeId,
        'territory_id' => $territoryId,
        'country_id' => $countryId,
        'status' => 'published', 'moderation_status' => 'approved',
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

function publicTerritoryGivePlacement(Object_ $object, string $badgeText): void
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
        'placement_tier_id' => $tierId, 'price' => 10, 'currency' => 'EUR',
        'validity_days' => 30, 'bump_allowed' => false, 'is_active' => true,
        'display_order' => $rank, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('object_placements')->insert([
        'object_id' => $object->id, 'placement_package_id' => $packageId,
        'starts_at' => now()->subDays(1)->toDateString(), 'ends_at' => now()->addDays(30)->toDateString(),
        'internal_priority' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

it('composes breadcrumb, hero, description, a catalog block per active type, and child navigation — omitting a type with nothing to show', function (): void {
    $fixture = publicTerritoryRegistry();
    $country = publicTerritoryMake($fixture['countryId'], 'Moldova Country Root');
    $region = publicTerritoryMake($fixture['countryId'], 'Central Region', $country->id);
    $city = publicTerritoryMake($fixture['countryId'], 'Capital City', $region->id);

    publicTerritoryMakeObject($fixture['countryId'], $city->id, $fixture['hotelTypeId'], 'Capital Grand Hotel');
    // No restaurant seeded — that block must be omitted entirely.

    $response = $this->get(publicTerritoryUrl($city));

    $response->assertOk()
        ->assertSeeInOrder(['Moldova Country Root', 'Central Region', 'Capital City'])
        ->assertSee('Capital City is a lovely place to visit.')
        ->assertSee('Everything a visitor needs to know about Capital City.')
        ->assertSee('Hotel')
        ->assertSee('Capital Grand Hotel');

    // "Restaurant" legitimately still appears in the shell's own site-wide
    // navigation (every active type is always listed there); what must be
    // absent is this territory page's own catalog-block heading for it.
    $response->assertDontSee('<h2 class="text-xl font-semibold text-ink">Restaurant</h2>', false);
});

it('scopes transitively — an object on a descendant node appears in an ancestor territory page catalog block', function (): void {
    $fixture = publicTerritoryRegistry();
    $region = publicTerritoryMake($fixture['countryId'], 'Transitive Region');
    $city = publicTerritoryMake($fixture['countryId'], 'Transitive City', $region->id);

    publicTerritoryMakeObject($fixture['countryId'], $city->id, $fixture['hotelTypeId'], 'Deep Descendant Hotel');

    $response = $this->get(publicTerritoryUrl($region));

    $response->assertOk()->assertSee('Deep Descendant Hotel');
});

it('restarts tier ordering per territory — the same tier distribution under two sibling territories orders independently', function (): void {
    $fixture = publicTerritoryRegistry();
    $siblingA = publicTerritoryMake($fixture['countryId'], 'Sibling Territory A');
    $siblingB = publicTerritoryMake($fixture['countryId'], 'Sibling Territory B');

    $aStandard = publicTerritoryMakeObject($fixture['countryId'], $siblingA->id, $fixture['hotelTypeId'], 'A Standard Hotel');
    $aVip = publicTerritoryMakeObject($fixture['countryId'], $siblingA->id, $fixture['hotelTypeId'], 'A VIP Hotel');
    publicTerritoryGivePlacement($aVip, 'VIP');

    $bStandard = publicTerritoryMakeObject($fixture['countryId'], $siblingB->id, $fixture['hotelTypeId'], 'B Standard Hotel');
    $bVip = publicTerritoryMakeObject($fixture['countryId'], $siblingB->id, $fixture['hotelTypeId'], 'B VIP Hotel');
    publicTerritoryGivePlacement($bVip, 'VIP');

    $responseA = (string) $this->get(publicTerritoryUrl($siblingA))->getContent();
    $responseB = (string) $this->get(publicTerritoryUrl($siblingB))->getContent();

    // Each territory's own VIP object outranks its own standard object —
    // proven independently per page, not merely that a VIP exists somewhere.
    expect(strpos($responseA, 'A VIP Hotel'))->toBeLessThan(strpos($responseA, 'A Standard Hotel'));
    expect(strpos($responseB, 'B VIP Hotel'))->toBeLessThan(strpos($responseB, 'B Standard Hotel'));

    expect($aStandard->id)->not->toBe($bStandard->id);
});

/**
 * Places a banner in the given slot, targeted at $territoryId when given
 * (null means untargeted — every territory matches).
 */
function publicTerritoryMakeBanner(string $slotKey, ?int $territoryId, string $linkText = 'Advertiser banner'): Banner
{
    $slotId = DB::table('banner_slots')->insertGetId([
        'key' => $slotKey, 'surfaces' => json_encode(['country', 'region', 'city', 'resort', 'category']),
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $bannerId = DB::table('banners')->insertGetId([
        'banner_slot_id' => $slotId, 'name' => "Banner for {$slotKey}", 'advertiser' => 'Acme',
        'destination_link' => 'https://example.test', 'display_order' => 0,
        'starts_at' => now()->subDay()->toDateString(), 'ends_at' => now()->addMonth()->toDateString(),
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('banner_translations')->insert([
        'banner_id' => $bannerId, 'locale' => 'en', 'link_text' => $linkText,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    if ($territoryId !== null) {
        DB::table('banner_targets')->insert([
            'banner_id' => $bannerId, 'target_type' => (new Territory)->getMorphClass(), 'target_id' => $territoryId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    return Banner::query()->findOrFail($bannerId);
}

it('renders a banner targeted at this territory in the top, mid, and bottom slots', function (): void {
    // F-24/S-18: BannerSelectionService::forSlot() had exactly one caller in
    // the whole codebase before this fix, and it only ever requested the
    // three home-page slots — no territory page rendered a banner at all,
    // regardless of what advertising inventory existed.
    $fixture = publicTerritoryRegistry();
    $territory = publicTerritoryMake($fixture['countryId'], 'Bannered Territory');
    publicTerritoryMakeObject($fixture['countryId'], $territory->id, $fixture['hotelTypeId'], 'Filler Hotel');
    publicTerritoryMakeObject($fixture['countryId'], $territory->id, $fixture['restaurantTypeId'], 'Filler Restaurant');

    $top = publicTerritoryMakeBanner('territory-top', $territory->id, 'Top banner link');
    $mid = publicTerritoryMakeBanner('territory-mid', $territory->id, 'Mid banner link');
    $bottom = publicTerritoryMakeBanner('territory-bottom', $territory->id, 'Bottom banner link');

    $html = (string) $this->get(publicTerritoryUrl($territory))->assertOk()->getContent();

    expect($html)->toContain(route('banners.click', ['banner' => $top->id]))
        ->and($html)->toContain(route('banners.click', ['banner' => $mid->id]))
        ->and($html)->toContain(route('banners.click', ['banner' => $bottom->id]));
});

it('never shows a banner targeted at a different territory — the resort-scoped-only guarantee', function (): void {
    $fixture = publicTerritoryRegistry();
    $ownTerritory = publicTerritoryMake($fixture['countryId'], 'Own Territory');
    $otherTerritory = publicTerritoryMake($fixture['countryId'], 'Unrelated Other Territory');
    publicTerritoryMakeObject($fixture['countryId'], $ownTerritory->id, $fixture['hotelTypeId'], 'Own Territory Hotel');

    $otherBanner = publicTerritoryMakeBanner('territory-top', $otherTerritory->id, 'Unrelated territory banner');

    $html = (string) $this->get(publicTerritoryUrl($ownTerritory))->assertOk()->getContent();

    expect($html)->not->toContain(route('banners.click', ['banner' => $otherBanner->id]));
});

it('renders a banner on the typed-catalog page, targeted by both territory and category', function (): void {
    $fixture = publicTerritoryRegistry();
    $territory = publicTerritoryMake($fixture['countryId'], 'Typed Catalog Territory');
    publicTerritoryMakeObject($fixture['countryId'], $territory->id, $fixture['hotelTypeId'], 'Typed Catalog Hotel');

    $banner = publicTerritoryMakeBanner('typed-catalog-top', $territory->id, 'Typed catalog banner');

    /** @var ObjectType $hotelType */
    $hotelType = ObjectType::query()->findOrFail($fixture['hotelTypeId']);
    $url = app(PublicUrlGenerator::class)->typedCatalogUrl($territory, $hotelType);

    $html = (string) $this->get((string) $url)->assertOk()->getContent();

    expect($html)->toContain(route('banners.click', ['banner' => $banner->id]));
});

it('404s an inactive territory', function (): void {
    $fixture = publicTerritoryRegistry();
    $territory = publicTerritoryMake($fixture['countryId'], 'Inactive Territory');
    $territory->update(['is_active' => false]);

    $this->get(publicTerritoryUrl($territory))->assertNotFound();
});
