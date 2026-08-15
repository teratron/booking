<?php

declare(strict_types=1);

use App\Models\Territory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Public News Feed & Promotions
|--------------------------------------------------------------------------
|
| A portal-wide news feed and each item's own detail page — a news item
| past its own end date drops from the feed but its own page stays
| reachable, and pinned items sort first. Promotions have no listing of
| their own (always territory/object-scoped); an elapsed one drops from
| every section that lists it, but a symmetrical reachable-page contract
| holds for its own detail route too.
|
*/

/** @return array{languageId: int, countryId: int, territoryId: int, objectId: int} */
function publicNewsRegistry(): array
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
    DB::table('territory_translations')->insert([
        'territory_id' => $territoryId, 'country_id' => $countryId, 'locale' => 'en', 'name' => 'News Territory', 'slug' => 'news-territory',
        'full_slug_path' => 'news-territory',
        'needs_review' => false, 'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    $objectTypeId = DB::table('object_types')->insertGetId([
        'key' => 'hotel', 'is_active' => true, 'has_rooms' => true, 'has_availability_status' => true,
        'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('object_type_translations')->insert([
        'object_type_id' => $objectTypeId, 'locale' => 'en', 'name' => 'Hotel', 'slug' => 'hotel',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $objectId = DB::table('objects')->insertGetId([
        'ulid' => (string) Str::ulid(), 'owner_id' => User::factory()->create()->id,
        'object_type_id' => $objectTypeId, 'territory_id' => $territoryId, 'country_id' => $countryId,
        'status' => 'published', 'moderation_status' => 'approved',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('object_translations')->insert([
        'object_id' => $objectId, 'locale' => 'en', 'name' => 'Promoted Hotel',
        'slug' => 'promoted-hotel-'.$objectId, 'needs_review' => false, 'published_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('modules')->insert([
        'key' => 'reviews', 'default_state' => 'enabled',
        'scopable_levels' => json_encode(['portal', 'country', 'category', 'object']),
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return compact('languageId', 'countryId', 'territoryId', 'objectId');
}

/** @param  array<string, mixed>  $overrides */
function publicNewsMakeItem(string $title, array $overrides = []): int
{
    $newsId = DB::table('news_items')->insertGetId(array_merge([
        'author_id' => User::factory()->create()->id,
        'status' => 'published', 'moderation_status' => 'approved', 'is_pinned' => false,
        'publish_at' => now()->subDay(),
        'created_at' => now(), 'updated_at' => now(),
    ], $overrides));

    DB::table('news_translations')->insert([
        'news_item_id' => $newsId, 'locale' => 'en', 'title' => $title,
        'body' => 'The full body of the news item.', 'slug' => Str::slug($title).'-'.$newsId,
        'needs_review' => false, 'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);

    return $newsId;
}

/** @param  array<string, mixed>  $overrides */
function publicNewsMakePromotion(array $fixture, string $title, array $overrides = []): int
{
    $promotionId = DB::table('promotions')->insertGetId(array_merge([
        'object_id' => $fixture['objectId'], 'territory_id' => $fixture['territoryId'],
        'starts_at' => now()->subDays(10)->toDateString(), 'ends_at' => now()->addDays(10)->toDateString(),
        'status' => 'published', 'moderation_status' => 'approved',
        'created_at' => now(), 'updated_at' => now(),
    ], $overrides));

    DB::table('promotion_translations')->insert([
        'promotion_id' => $promotionId, 'locale' => 'en', 'title' => $title,
        'summary' => 'A short promotion summary.', 'slug' => Str::slug($title).'-'.$promotionId,
        'needs_review' => false, 'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);

    return $promotionId;
}

it('lists published news in the feed, sorts pinned items first, and excludes an elapsed item — while its own page stays reachable', function (): void {
    publicNewsRegistry();
    $olderPinnedId = publicNewsMakeItem('Older Pinned News', ['is_pinned' => true, 'publish_at' => now()->subDays(5)]);
    $newerUnpinnedId = publicNewsMakeItem('Newer Unpinned News', ['publish_at' => now()->subDay()]);
    $elapsedId = publicNewsMakeItem('Elapsed News', ['end_at' => now()->subDay()]);

    $feedResponse = $this->get(route('public.news.index', ['lang' => 'en']));
    $feedHtml = (string) $feedResponse->getContent();

    $feedResponse->assertOk()->assertDontSee('Elapsed News');
    expect(strpos($feedHtml, 'Older Pinned News'))->toBeLessThan(strpos($feedHtml, 'Newer Unpinned News'));

    // Dropped from the feed, but its own page is still reachable.
    $this->get(route('public.news.show', ['lang' => 'en', 'newsItem' => $elapsedId]))
        ->assertOk()
        ->assertSee('Elapsed News');

    $this->get(route('public.news.show', ['lang' => 'en', 'newsItem' => $olderPinnedId]))
        ->assertOk()
        ->assertSee('Older Pinned News');
});

it('renders an empty state when no news has been published yet', function (): void {
    publicNewsRegistry();

    $this->get(route('public.news.index', ['lang' => 'en']))
        ->assertOk()
        ->assertSee(__('public.news.empty'));
});

it('makes a draft or withdrawn news item unreachable on its own page', function (): void {
    publicNewsRegistry();
    $draftId = publicNewsMakeItem('Draft News', ['status' => 'draft']);
    $withdrawnId = publicNewsMakeItem('Withdrawn News', ['status' => 'withdrawn']);

    $this->get(route('public.news.show', ['lang' => 'en', 'newsItem' => $draftId]))->assertNotFound();
    $this->get(route('public.news.show', ['lang' => 'en', 'newsItem' => $withdrawnId]))->assertNotFound();
});

it('excludes an elapsed promotion from the section that lists it, proven on the territory page, while its own page stays reachable', function (): void {
    $fixture = publicNewsRegistry();
    $validId = publicNewsMakePromotion($fixture, 'Valid Promotion');
    $elapsedId = publicNewsMakePromotion($fixture, 'Elapsed Promotion', [
        'starts_at' => now()->subDays(20)->toDateString(), 'ends_at' => now()->subDay()->toDateString(),
    ]);

    $territoryResponse = $this->get(publicTerritoryUrl(Territory::query()->findOrFail($fixture['territoryId'])));

    $territoryResponse->assertOk()
        ->assertSee('Valid Promotion')
        ->assertDontSee('Elapsed Promotion');

    // Dropped from the section, but its own page is still reachable —
    // its own `status` has not been transitioned by the (separately
    // owned) archival job in this fixture.
    $this->get(route('public.promotions.show', ['lang' => 'en', 'promotion' => $elapsedId]))
        ->assertOk()
        ->assertSee('Elapsed Promotion');
});

it('makes a draft or archived promotion unreachable on its own page', function (): void {
    $fixture = publicNewsRegistry();
    $draftId = publicNewsMakePromotion($fixture, 'Draft Promotion', ['status' => 'draft']);
    $archivedId = publicNewsMakePromotion($fixture, 'Archived Promotion', ['status' => 'archived']);

    $this->get(route('public.promotions.show', ['lang' => 'en', 'promotion' => $draftId]))->assertNotFound();
    $this->get(route('public.promotions.show', ['lang' => 'en', 'promotion' => $archivedId]))->assertNotFound();
});
