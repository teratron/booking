<?php

declare(strict_types=1);

use App\Models\Object_;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

// The public-shell cache is keyed partly by database id, and RefreshDatabase
// resets auto-increment between test cases — without this, a later test can
// read another test's stale cached destinations/cities list back under the
// same id. Fixtures here also insert via the query builder directly, which
// bypasses the Eloquent write hooks that invalidate this cache in production.
beforeEach(fn () => Cache::flush());

/*
|--------------------------------------------------------------------------
| Public Home Page
|--------------------------------------------------------------------------
|
| The home page owns no domain data — every block is a view onto another
| spec's data, resolved against the visitor's selected country, and
| omitted entirely (never an empty frame) when it has nothing to show.
|
*/

/** @return array{languageId: int, countryId: int, otherCountryId: int, hotelTypeId: int} */
function publicHomeRegistry(): array
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
    $otherCountryId = DB::table('countries')->insertGetId([
        'code' => 'GE', 'currency' => 'GEL', 'phone_code' => '+995',
        'primary_language_id' => $languageId, 'is_active' => true, 'display_order' => 2,
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

    return compact('languageId', 'countryId', 'otherCountryId', 'hotelTypeId');
}

/** @return array{id: int, name: string} territory fixture for $countryId, top-level and featured */
function publicHomeMakeFeaturedTerritory(int $countryId, string $name): array
{
    $levelId = DB::table('territory_levels')->insertGetId([
        'country_id' => $countryId, 'depth_rank' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $territoryId = DB::table('territories')->insertGetId([
        'country_id' => $countryId, 'level_id' => $levelId, 'is_active' => true, 'is_featured' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $slug = Str::slug($name);
    DB::table('territory_translations')->insert([
        'territory_id' => $territoryId, 'country_id' => $countryId, 'locale' => 'en', 'name' => $name, 'slug' => $slug,
        'full_slug_path' => $slug,
        'needs_review' => false, 'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);

    return ['id' => $territoryId, 'name' => $name];
}

/**
 * Creates a fresh, non-featured territory for $countryId — objects need a
 * territory_id, but these fixtures do not exercise territory-specific
 * behaviour. Not memoized across calls: a `static` cache would survive
 * `RefreshDatabase`'s reset between test cases and hand back an id from a
 * database the current test never seeded.
 */
function publicHomeDefaultTerritoryId(int $countryId): int
{
    $levelId = DB::table('territory_levels')->insertGetId([
        'country_id' => $countryId, 'depth_rank' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $territoryId = DB::table('territories')->insertGetId([
        'country_id' => $countryId, 'level_id' => $levelId, 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('territory_translations')->insert([
        'territory_id' => $territoryId, 'country_id' => $countryId, 'locale' => 'en', 'name' => 'Default Territory '.$territoryId,
        'slug' => 'default-territory-'.$territoryId,
        'full_slug_path' => 'default-territory-'.$territoryId,
        'needs_review' => false, 'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);

    return $territoryId;
}

/** @param  array<string, mixed>  $overrides */
function publicHomeMakeObject(int $countryId, int $typeId, string $name, array $overrides = []): Object_
{
    $objectId = DB::table('objects')->insertGetId(array_merge([
        'ulid' => (string) Str::ulid(),
        'owner_id' => User::factory()->create()->id,
        'object_type_id' => $typeId,
        'territory_id' => publicHomeDefaultTerritoryId($countryId),
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

it('renders every block for a fixture with content in each, and omits blocks with nothing to show', function (): void {
    $fixture = publicHomeRegistry();
    $destination = publicHomeMakeFeaturedTerritory($fixture['countryId'], 'Featured Destination');

    // A featured city (non-top-level, featured) for "popular cities".
    $regionId = DB::table('territories')->insertGetId([
        'country_id' => $fixture['countryId'],
        'level_id' => DB::table('territory_levels')->insertGetId([
            'country_id' => $fixture['countryId'], 'depth_rank' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]),
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $cityId = DB::table('territories')->insertGetId([
        'country_id' => $fixture['countryId'], 'parent_id' => $regionId,
        'level_id' => DB::table('territory_levels')->insertGetId([
            'country_id' => $fixture['countryId'], 'depth_rank' => 2, 'created_at' => now(), 'updated_at' => now(),
        ]),
        'is_active' => true, 'is_featured' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('territory_translations')->insert([
        'territory_id' => $cityId, 'country_id' => $fixture['countryId'], 'locale' => 'en', 'name' => 'Featured City', 'slug' => 'featured-city',
        'full_slug_path' => 'featured-city',
        'needs_review' => false, 'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);

    publicHomeMakeObject($fixture['countryId'], $fixture['hotelTypeId'], 'Recommended Hotel');
    publicHomeMakeObject($fixture['countryId'], $fixture['hotelTypeId'], 'Freshly Added Hotel');

    $newsId = DB::table('news_items')->insertGetId([
        'author_id' => User::factory()->create()->id,
        'status' => 'published', 'moderation_status' => 'approved', 'is_pinned' => false, 'publish_at' => now()->subDay(),
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('news_translations')->insert([
        'news_item_id' => $newsId, 'locale' => 'en', 'title' => 'Portal News Item', 'body' => 'A short news update.',
        'slug' => 'portal-news-item',
        'needs_review' => false, 'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);

    $reviewedObject = publicHomeMakeObject($fixture['countryId'], $fixture['hotelTypeId'], 'Reviewed Hotel');
    DB::table('reviews')->insert([
        'object_id' => $reviewedObject->id, 'rating' => 5, 'body' => 'Wonderful stay, highly recommended.',
        'status' => 'published', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $response = $this->get('/en');

    $response->assertOk()
        ->assertSee(__('public.home.hero.title'))
        ->assertSee('Featured Destination')
        ->assertSee('Hotel')
        ->assertSee('Recommended Hotel')
        ->assertSee('Freshly Added Hotel')
        ->assertSee('Featured City')
        ->assertSee('Portal News Item')
        ->assertSee('Reviewed Hotel')
        ->assertSee(__('public.home.informational.title'));

    // Nothing seeded for promotions, articles, or partners — each omitted
    // entirely rather than rendered as an empty section. The bare
    // "Promotions" string also appears in the shell's own nav bar, so this
    // asserts against the block's own heading markup, not the translated word.
    $response->assertDontSee('<h2 class="text-xl font-semibold text-ink">'.__('public.territory.promotions').'</h2>', false)
        ->assertDontSee('<h2 class="text-xl font-semibold text-ink">'.__('public.home.partners_heading').'</h2>', false);
});

it('sees curated destinations, cities, and computed content scoped to the selected country, not another', function (): void {
    $fixture = publicHomeRegistry();
    publicHomeMakeFeaturedTerritory($fixture['countryId'], 'Own Country Destination');
    publicHomeMakeFeaturedTerritory($fixture['otherCountryId'], 'Other Country Destination');

    publicHomeMakeObject($fixture['countryId'], $fixture['hotelTypeId'], 'Own Country Hotel');
    publicHomeMakeObject($fixture['otherCountryId'], $fixture['hotelTypeId'], 'Other Country Hotel');

    $this->post('/en/country', ['country' => 'MD']);

    $response = $this->get('/en');
    $response->assertOk()
        ->assertSee('Own Country Hotel')
        ->assertDontSee('Other Country Hotel');

    // The footer's own "popular destinations" list is deliberately
    // portal-wide (not country-scoped), so "Other Country Destination"
    // legitimately appears there on every page — this narrows the
    // assertion to the home page's own country-scoped block instead of
    // the whole response body.
    $html = (string) $response->getContent();
    $blockStart = strpos($html, '<h2 class="text-xl font-semibold text-ink">'.__('public.territory.explore').'</h2>');
    expect($blockStart)->not->toBeFalse();
    $blockEnd = strpos($html, '</section>', $blockStart);
    $block = substr($html, $blockStart, $blockEnd - $blockStart);

    expect($block)->toContain('Own Country Destination')
        ->and($block)->not->toContain('Other Country Destination');
});

it('tier-orders the recommended and newest-objects blocks through CatalogQueryService, never a second implementation', function (): void {
    $fixture = publicHomeRegistry();
    $standard = publicHomeMakeObject($fixture['countryId'], $fixture['hotelTypeId'], 'Standard Ranked Hotel');
    $vip = publicHomeMakeObject($fixture['countryId'], $fixture['hotelTypeId'], 'VIP Ranked Hotel');

    $tierId = DB::table('placement_tiers')->insertGetId([
        'rank' => 1, 'border_colour' => '#f8bb44', 'badge_colour' => '#f8bb44',
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('placement_tier_translations')->insert([
        'placement_tier_id' => $tierId, 'locale' => 'en', 'label' => 'VIP', 'badge_text' => 'VIP',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $packageId = DB::table('placement_packages')->insertGetId([
        'placement_tier_id' => $tierId, 'price' => 10, 'currency' => 'EUR', 'validity_days' => 30,
        'bump_allowed' => false, 'is_active' => true, 'display_order' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('object_placements')->insert([
        'object_id' => $vip->id, 'placement_package_id' => $packageId,
        'starts_at' => now()->subDays(1)->toDateString(), 'ends_at' => now()->addDays(30)->toDateString(),
        'internal_priority' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->post('/en/country', ['country' => 'MD']);
    $html = (string) $this->get('/en')->getContent();

    expect(strpos($html, 'VIP Ranked Hotel'))->toBeLessThan(strpos($html, 'Standard Ranked Hotel'));
});

it('resolves the home page in one server pass, under a sensible query-count ceiling', function (): void {
    $fixture = publicHomeRegistry();
    publicHomeMakeFeaturedTerritory($fixture['countryId'], 'Ceiling Destination');
    publicHomeMakeObject($fixture['countryId'], $fixture['hotelTypeId'], 'Ceiling Hotel');

    $this->post('/en/country', ['country' => 'MD']);

    $queryCount = 0;
    DB::listen(function () use (&$queryCount): void {
        $queryCount++;
    });

    $this->get('/en')->assertOk();

    expect($queryCount)->toBeLessThanOrEqual(60);
});
