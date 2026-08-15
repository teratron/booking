<?php

declare(strict_types=1);

use App\Models\Object_;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Public Object Related Content
|--------------------------------------------------------------------------
|
| The object page's last three blocks: the object's own news and
| promotions (through the shared content card), and nearby/similar
| objects — both tier-ordered through CatalogQueryService, never a
| bespoke query, and each omitted entirely when it has nothing to show.
|
*/

/** @return array{languageId: int, countryId: int, territoryAId: int, territoryBId: int, hotelTypeId: int, restaurantTypeId: int} */
function publicObjectRelatedRegistry(): array
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
    $territoryAId = DB::table('territories')->insertGetId([
        'country_id' => $countryId, 'level_id' => $levelId, 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('territory_translations')->insert([
        'territory_id' => $territoryAId, 'country_id' => $countryId, 'locale' => 'en', 'name' => 'Territory A', 'slug' => 'territory-a',
        'full_slug_path' => 'territory-a',
        'needs_review' => false, 'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    $territoryBId = DB::table('territories')->insertGetId([
        'country_id' => $countryId, 'level_id' => $levelId, 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('territory_translations')->insert([
        'territory_id' => $territoryBId, 'country_id' => $countryId, 'locale' => 'en', 'name' => 'Territory B', 'slug' => 'territory-b',
        'full_slug_path' => 'territory-b',
        'needs_review' => false, 'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    $hotelTypeId = DB::table('object_types')->insertGetId([
        'key' => 'hotel', 'is_active' => true, 'has_rooms' => true, 'has_availability_status' => true,
        'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('object_type_translations')->insert([
        'object_type_id' => $hotelTypeId, 'locale' => 'en', 'name' => 'Hotel', 'slug' => 'hotel',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $restaurantTypeId = DB::table('object_types')->insertGetId([
        'key' => 'restaurant', 'is_active' => true, 'has_rooms' => false, 'has_availability_status' => false,
        'display_order' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('object_type_translations')->insert([
        'object_type_id' => $restaurantTypeId, 'locale' => 'en', 'name' => 'Restaurant', 'slug' => 'restaurant',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('modules')->insert([
        'key' => 'reviews', 'default_state' => 'enabled',
        'scopable_levels' => json_encode(['portal', 'country', 'category', 'object']),
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return compact('languageId', 'countryId', 'territoryAId', 'territoryBId', 'hotelTypeId', 'restaurantTypeId');
}

/** @param  array<string, mixed>  $overrides */
function publicObjectRelatedMake(array $fixture, int $territoryId, int $typeId, string $name, array $overrides = []): Object_
{
    $objectId = DB::table('objects')->insertGetId(array_merge([
        'ulid' => (string) Str::ulid(),
        'owner_id' => User::factory()->create()->id,
        'object_type_id' => $typeId,
        'territory_id' => $territoryId,
        'country_id' => $fixture['countryId'],
        'status' => 'published', 'moderation_status' => 'approved',
        'availability_status' => 'available',
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

function publicObjectRelatedGivePlacement(Object_ $object, string $badgeText = 'VIP'): void
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

it('renders nearby objects tier-ordered through CatalogQueryService — a lower-tier nearby object never outranks a higher-tier one', function (): void {
    $fixture = publicObjectRelatedRegistry();
    $main = publicObjectRelatedMake($fixture, $fixture['territoryAId'], $fixture['hotelTypeId'], 'Main Hotel');
    $lowerTier = publicObjectRelatedMake($fixture, $fixture['territoryAId'], $fixture['hotelTypeId'], 'Standard Nearby Hotel');
    $higherTier = publicObjectRelatedMake($fixture, $fixture['territoryAId'], $fixture['hotelTypeId'], 'VIP Nearby Hotel');
    publicObjectRelatedGivePlacement($higherTier, 'VIP');

    $html = (string) $this->get(publicObjectUrl($main))->getContent();

    expect(strpos($html, 'VIP Nearby Hotel'))->toBeLessThan(strpos($html, 'Standard Nearby Hotel'));
});

it("scopes nearby to the object's own territory and similar to the object's own type — never the other way around", function (): void {
    $fixture = publicObjectRelatedRegistry();
    $main = publicObjectRelatedMake($fixture, $fixture['territoryAId'], $fixture['hotelTypeId'], 'Main Hotel');

    // Same territory, different type — belongs in "nearby", not "similar".
    $nearbyDifferentType = publicObjectRelatedMake($fixture, $fixture['territoryAId'], $fixture['restaurantTypeId'], 'Nearby Restaurant');

    // Different territory, same type, same country — belongs in "similar", not "nearby".
    $similarDifferentTerritory = publicObjectRelatedMake($fixture, $fixture['territoryBId'], $fixture['hotelTypeId'], 'Similar Hotel Elsewhere');

    $response = $this->get(publicObjectUrl($main));
    $html = (string) $response->getContent();

    $response->assertOk()
        ->assertSee(__('public.object.nearby_heading'))
        ->assertSee(__('public.object.similar_heading'))
        ->assertSee('Nearby Restaurant')
        ->assertSee('Similar Hotel Elsewhere');

    $nearbyStart = strpos($html, '<h2 class="text-xl font-semibold text-ink">'.__('public.object.nearby_heading').'</h2>');
    $similarStart = strpos($html, '<h2 class="text-xl font-semibold text-ink">'.__('public.object.similar_heading').'</h2>');
    expect($nearbyStart)->not->toBeFalse()->and($similarStart)->not->toBeFalse();

    $nearbyBlock = substr($html, $nearbyStart, $similarStart - $nearbyStart);
    $similarBlock = substr($html, $similarStart);

    expect($nearbyBlock)->toContain('Nearby Restaurant')
        ->and($nearbyBlock)->not->toContain('Similar Hotel Elsewhere')
        ->and($similarBlock)->toContain('Similar Hotel Elsewhere')
        ->and($similarBlock)->not->toContain('Nearby Restaurant');
});

it("renders the object's own published news and promotions through the shared content card, and omits every related section with nothing to show", function (): void {
    $fixture = publicObjectRelatedRegistry();
    $withContent = publicObjectRelatedMake($fixture, $fixture['territoryAId'], $fixture['hotelTypeId'], 'Content Hotel');
    $bare = publicObjectRelatedMake($fixture, $fixture['territoryAId'], $fixture['hotelTypeId'], 'Bare Hotel');

    $newsId = DB::table('news_items')->insertGetId([
        'author_id' => User::factory()->create()->id, 'object_id' => $withContent->id,
        'status' => 'published', 'moderation_status' => 'approved', 'is_pinned' => false,
        'publish_at' => now()->subDay(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('news_translations')->insert([
        'news_item_id' => $newsId, 'locale' => 'en', 'title' => 'Object-specific News',
        'summary' => 'A short summary of the news.', 'body' => 'The full body of the news item.',
        'slug' => 'object-specific-news', 'needs_review' => false, 'published_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $promotionId = DB::table('promotions')->insertGetId([
        'object_id' => $withContent->id, 'territory_id' => $fixture['territoryAId'],
        'starts_at' => now()->subDay()->toDateString(), 'ends_at' => now()->addDays(30)->toDateString(),
        'status' => 'published', 'moderation_status' => 'approved',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('promotion_translations')->insert([
        'promotion_id' => $promotionId, 'locale' => 'en', 'title' => 'Object-specific Promotion',
        'summary' => 'A short summary of the promotion.', 'slug' => 'object-specific-promotion',
        'needs_review' => false, 'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);

    $withContentResponse = $this->get(publicObjectUrl($withContent));
    $withContentResponse->assertOk()
        ->assertSee(__('public.shell.nav.news'))
        ->assertSee('Object-specific News')
        ->assertSee('A short summary of the news.')
        ->assertSee(__('public.territory.promotions'))
        ->assertSee('Object-specific Promotion')
        ->assertSee('A short summary of the promotion.');

    // $bare shares a territory and type with $withContent, so its own
    // nearby/similar blocks are not empty — only its news/promotions are,
    // since those are scoped to this object's own id, not its territory
    // or type.
    $bareResponse = $this->get(publicObjectUrl($bare));
    $bareResponse->assertOk()
        ->assertDontSee('Object-specific News')
        ->assertDontSee('Object-specific Promotion');
});

it('omits the nearby and similar blocks entirely when no other object shares this territory or type', function (): void {
    $fixture = publicObjectRelatedRegistry();
    $isolated = publicObjectRelatedMake($fixture, $fixture['territoryBId'], $fixture['restaurantTypeId'], 'Isolated Restaurant');

    $response = $this->get(publicObjectUrl($isolated));

    // "News" and "Promotions" also appear in the shell's own site-wide
    // nav bar, so these two assert the block's own heading markup rather
    // than the bare translated word — the same false-positive class
    // already fixed once for "Restaurant" and once for "Promotions" on
    // other public-site pages this session.
    $response->assertOk()
        ->assertDontSee(__('public.object.nearby_heading'))
        ->assertDontSee(__('public.object.similar_heading'))
        ->assertDontSee('<h2 class="text-xl font-semibold text-ink">'.__('public.shell.nav.news').'</h2>', false)
        ->assertDontSee('<h2 class="text-xl font-semibold text-ink">'.__('public.territory.promotions').'</h2>', false);
});
