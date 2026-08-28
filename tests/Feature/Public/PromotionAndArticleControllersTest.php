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
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Promotion Detail Page & Article API Filters
|--------------------------------------------------------------------------
|
| Two independent surfaces sharing one file because both had the identical
| shape of gap: the "reject" branch was already exercised elsewhere, but
| the actual success path — a promotion's own detail page rendering for a
| visitor, and the article API's query-string filters actually narrowing
| the collection — never ran.
|
*/

/** @return array{languageId: int, countryId: int, territoryId: int, objectId: int, objectName: string} */
function promotionAndArticleRegistry(): array
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
        'territory_id' => $territoryId, 'country_id' => $countryId, 'locale' => 'en',
        'name' => 'Promo Territory', 'slug' => 'promo-territory', 'full_slug_path' => 'promo-territory',
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
    $objectName = 'Promoted Grand Hotel';
    DB::table('object_translations')->insert([
        'object_id' => $objectId, 'locale' => 'en', 'name' => $objectName,
        'slug' => 'promoted-grand-hotel-'.$objectId, 'needs_review' => false, 'published_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('modules')->insert([
        'key' => 'reviews', 'default_state' => 'enabled',
        'scopable_levels' => json_encode(['portal', 'country', 'category', 'object']),
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return compact('languageId', 'countryId', 'territoryId', 'objectId', 'objectName');
}

/**
 * @param  array{objectId: int, territoryId: int}  $fixture
 * @param  array<string, mixed>  $overrides
 */
function promotionAndArticleMakePromotion(array $fixture, string $title, array $overrides = []): int
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

it('renders a valid, publicly-visible promotion on its own detail page', function (): void {
    $fixture = promotionAndArticleRegistry();
    $promotionId = promotionAndArticleMakePromotion($fixture, 'Summer Discount');

    $response = $this->get(route('public.promotions.show', ['lang' => 'en', 'slug' => "summer-discount-{$promotionId}"]));

    $response->assertOk()
        ->assertSee('Summer Discount')
        // The object breadcrumb/link — proves $object relation was loaded
        // and its own translated name resolved, not just the promotion row.
        ->assertSee($fixture['objectName'])
        // The territory link — proves the territory relation resolved too.
        ->assertSee('Promo Territory');
});

it('404s an expired promotion on its own detail page even though its status column has not yet caught up', function (): void {
    $fixture = promotionAndArticleRegistry();
    $expiredId = promotionAndArticleMakePromotion($fixture, 'Expired Deal', [
        'starts_at' => now()->subDays(20)->toDateString(), 'ends_at' => now()->subDay()->toDateString(),
    ]);

    $this->get(route('public.promotions.show', ['lang' => 'en', 'slug' => "expired-deal-{$expiredId}"]))
        ->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| Article API — category and tag query-string filters
|--------------------------------------------------------------------------
*/

/** @return array{languageId: int, categoryOneId: int, categoryTwoId: int, tagId: int, otherTagId: int} */
function promotionAndArticleApiFixture(): array
{
    $languageId = DB::table('languages')->insertGetId([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $categoryOneId = DB::table('article_categories')->insertGetId([
        'slug' => 'destinations', 'is_active' => true, 'display_order' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('article_category_translations')->insert([
        'article_category_id' => $categoryOneId, 'locale' => 'en', 'name' => 'Destinations',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $categoryTwoId = DB::table('article_categories')->insertGetId([
        'slug' => 'tips', 'is_active' => true, 'display_order' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('article_category_translations')->insert([
        'article_category_id' => $categoryTwoId, 'locale' => 'en', 'name' => 'Tips',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $tagId = DB::table('article_tags')->insertGetId([
        'slug' => 'winter', 'name' => 'Winter', 'is_active' => true, 'display_order' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $otherTagId = DB::table('article_tags')->insertGetId([
        'slug' => 'summer', 'name' => 'Summer', 'is_active' => true, 'display_order' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $moduleId = DB::table('modules')->insertGetId([
        'key' => 'api', 'default_state' => 'disabled', 'scopable_levels' => json_encode(['portal']),
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $module = Module::query()->findOrFail($moduleId);
    $moduleActor = User::factory()->create();
    app(ModuleAdministrator::class)->setState($module, 'portal', null, true, $moduleActor);

    return compact('languageId', 'categoryOneId', 'categoryTwoId', 'tagId', 'otherTagId');
}

/** @param  array<string, mixed>  $overrides */
function promotionAndArticleMakeArticle(string $title, array $overrides = []): int
{
    $articleId = DB::table('articles')->insertGetId(array_merge([
        'author_id' => User::factory()->create()->id,
        'status' => 'published', 'publish_at' => now()->subDay(),
        'created_at' => now(), 'updated_at' => now(),
    ], $overrides));

    DB::table('article_translations')->insert([
        'article_id' => $articleId, 'locale' => 'en', 'title' => $title,
        'summary' => 'A short article summary.', 'body' => 'The full article body.',
        'slug' => Str::slug($title).'-'.$articleId,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return $articleId;
}

function promotionAndArticleToken(): string
{
    $actor = User::factory()->create();
    $client = ApiClient::create(['name' => 'Article Filter Test Client', 'is_active' => true, 'created_by' => $actor->id]);

    $newAccessToken = app(ApiTokenService::class)->issue(
        $client, 'article-filter-test', [ApiResource::Articles], [], [], null, null, $actor,
    );

    return $newAccessToken->plainTextToken;
}

it('narrows the article collection by category from the query string', function (): void {
    $fixture = promotionAndArticleApiFixture();
    $destinationArticleId = promotionAndArticleMakeArticle('Destination Guide', ['article_category_id' => $fixture['categoryOneId']]);
    $tipsArticleId = promotionAndArticleMakeArticle('Packing Tips', ['article_category_id' => $fixture['categoryTwoId']]);

    $token = promotionAndArticleToken();

    $listedIds = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/articles?category={$fixture['categoryOneId']}")
        ->assertOk()
        ->json('data.*.id');

    expect($listedIds)->toBe([$destinationArticleId])
        ->and($listedIds)->not->toContain($tipsArticleId);
});

it('narrows the article collection by tag from the query string', function (): void {
    $fixture = promotionAndArticleApiFixture();
    $winterArticleId = promotionAndArticleMakeArticle('Winter Escapes');
    DB::table('article_tag')->insert(['article_id' => $winterArticleId, 'article_tag_id' => $fixture['tagId']]);

    $summerArticleId = promotionAndArticleMakeArticle('Summer Escapes');
    DB::table('article_tag')->insert(['article_id' => $summerArticleId, 'article_tag_id' => $fixture['otherTagId']]);

    $token = promotionAndArticleToken();

    $listedIds = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/articles?tag={$fixture['tagId']}")
        ->assertOk()
        ->json('data.*.id');

    expect($listedIds)->toBe([$winterArticleId])
        ->and($listedIds)->not->toContain($summerArticleId);
});

it('returns the unfiltered article collection when neither category nor tag is given', function (): void {
    promotionAndArticleApiFixture();
    $firstId = promotionAndArticleMakeArticle('First Article');
    $secondId = promotionAndArticleMakeArticle('Second Article');

    $token = promotionAndArticleToken();

    $listedIds = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/articles')
        ->assertOk()
        ->json('data.*.id');

    expect($listedIds)->toContain($firstId, $secondId);
});
