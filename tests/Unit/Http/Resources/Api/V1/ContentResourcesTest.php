<?php

declare(strict_types=1);

use App\Http\Resources\Api\V1\ArticleResource;
use App\Http\Resources\Api\V1\PromotionResource;
use App\Http\Resources\Api\V1\ReviewResource;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\ArticleTag;
use App\Models\Country;
use App\Models\Object_;
use App\Models\ObjectType;
use App\Models\Promotion;
use App\Models\Review;
use App\Models\Territory;
use App\Models\TerritoryLevel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| ArticleResource / PromotionResource / ReviewResource
|--------------------------------------------------------------------------
|
| Each resource is exercised directly against a real, persisted model
| instead of a bare array, so a renamed relation or column breaks this
| test exactly the way it would break the live API response. Every
| conditional branch the transformer itself carries — a missing category,
| no tags, no cover image, an anonymous review, a review with no owner
| reply — gets its own case, since those are exactly the null-handling
| paths a happy-path-only fixture would never exercise.
|
*/

function contentResourcesLanguageId(): int
{
    $existing = DB::table('languages')->where('code', 'en')->value('id');

    if ($existing !== null) {
        return (int) $existing;
    }

    return DB::table('languages')->insertGetId([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** @return array{territoryId: int, object: Object_} */
function contentResourcesGeography(): array
{
    $languageId = contentResourcesLanguageId();

    $country = Country::create([
        'code' => 'MD', 'currency' => 'MDL', 'phone_code' => '+373',
        'primary_language_id' => $languageId, 'is_active' => true,
    ]);

    $level = TerritoryLevel::create(['country_id' => $country->id, 'depth_rank' => 1]);

    $territory = Territory::create(['country_id' => $country->id, 'level_id' => $level->id]);

    $objectType = ObjectType::create(['key' => 'content-resource-probe-'.Str::random(8)]);

    $owner = User::factory()->create();

    $object = Object_::create([
        'ulid' => (string) Str::ulid(),
        'owner_id' => $owner->id,
        'object_type_id' => $objectType->id,
        'territory_id' => $territory->id,
        'country_id' => $country->id,
        'status' => 'published',
        'moderation_status' => 'approved',
    ]);

    return ['territoryId' => $territory->id, 'object' => $object];
}

/*
|--------------------------------------------------------------------------
| ArticleResource
|--------------------------------------------------------------------------
*/

it('renders the full article shape with a category, tags, and a cover image', function (): void {
    Storage::fake('public');

    contentResourcesLanguageId();

    $author = User::factory()->create();

    $category = ArticleCategory::create(['slug' => 'travel-guides', 'is_active' => true]);
    $category->translateOrNew('en')->name = 'Travel Guides';
    $category->save();

    $tagOne = ArticleTag::create(['slug' => 'family-friendly', 'name' => 'Family Friendly']);
    $tagTwo = ArticleTag::create(['slug' => 'budget', 'name' => 'Budget']);

    $publishAt = '2026-03-01T10:15:00+00:00';

    $article = Article::create([
        'author_id' => $author->id,
        'article_category_id' => $category->id,
        'status' => 'published',
        'publish_at' => $publishAt,
    ]);
    $article->translateOrNew('en')->fill([
        'title' => 'A Weekend in Chisinau',
        'summary' => 'Everything to see in two days.',
        'body' => 'The full article body goes here.',
        'slug' => 'a-weekend-in-chisinau',
    ]);
    $article->save();

    $article->tags()->attach([$tagOne->id, $tagTwo->id]);
    $article->addMedia(UploadedFile::fake()->image('cover.jpg'))->toMediaCollection('cover_image');

    $payload = (new ArticleResource($article))->toArray(request());

    expect($payload)->toBe([
        'id' => $article->id,
        'title' => 'A Weekend in Chisinau',
        'summary' => 'Everything to see in two days.',
        'body' => 'The full article body goes here.',
        'category' => 'Travel Guides',
        'tags' => ['Family Friendly', 'Budget'],
        'cover_image_url' => $article->getFirstMediaUrl('cover_image'),
        'publish_at' => $publishAt,
    ])
        ->and($payload['cover_image_url'])->not->toBeNull();
});

it('renders a null category, empty tags, no cover image, and a null publish date for a bare article', function (): void {
    contentResourcesLanguageId();

    $author = User::factory()->create();

    $article = Article::create([
        'author_id' => $author->id,
        'article_category_id' => null,
        'status' => 'draft',
        'publish_at' => null,
    ]);
    $article->translateOrNew('en')->fill([
        'title' => 'Untitled Draft',
        'summary' => null,
        'body' => 'Draft body.',
        'slug' => 'untitled-draft',
    ]);
    $article->save();

    $payload = (new ArticleResource($article))->toArray(request());

    expect($payload)->toBe([
        'id' => $article->id,
        'title' => 'Untitled Draft',
        'summary' => null,
        'body' => 'Draft body.',
        'category' => null,
        'tags' => [],
        'cover_image_url' => null,
        'publish_at' => null,
    ]);
});

/*
|--------------------------------------------------------------------------
| PromotionResource
|--------------------------------------------------------------------------
*/

it('renders the full promotion shape with an image', function (): void {
    Storage::fake('public');

    $geography = contentResourcesGeography();

    $promotion = Promotion::create([
        'object_id' => $geography['object']->id,
        'territory_id' => $geography['territoryId'],
        'starts_at' => '2026-04-01',
        'ends_at' => '2026-04-30',
        'status' => 'published',
        'moderation_status' => 'approved',
    ]);
    $promotion->translateOrNew('en')->fill([
        'title' => 'Spring Discount',
        'summary' => 'Fifteen percent off spring bookings.',
        'body' => 'Full promotion body.',
        'slug' => 'spring-discount',
    ]);
    $promotion->save();

    $promotion->addMedia(UploadedFile::fake()->image('promo.jpg'))->toMediaCollection('image');

    $payload = (new PromotionResource($promotion))->toArray(request());

    expect($payload)->toBe([
        'id' => $promotion->id,
        'title' => 'Spring Discount',
        'summary' => 'Fifteen percent off spring bookings.',
        'body' => 'Full promotion body.',
        'object_id' => $geography['object']->id,
        'territory_id' => $geography['territoryId'],
        'image_url' => $promotion->getFirstMediaUrl('image'),
        'starts_at' => '2026-04-01',
        'ends_at' => '2026-04-30',
    ])
        ->and($payload['image_url'])->not->toBeNull();
});

it('renders a null image url when a promotion has no creative attached', function (): void {
    $geography = contentResourcesGeography();

    $promotion = Promotion::create([
        'object_id' => $geography['object']->id,
        'territory_id' => $geography['territoryId'],
        'starts_at' => '2026-05-01',
        'ends_at' => '2026-05-31',
        'status' => 'draft',
        'moderation_status' => null,
    ]);
    $promotion->translateOrNew('en')->fill([
        'title' => 'Imageless Promotion',
        'summary' => 'No creative yet.',
        'body' => 'Body without an image.',
        'slug' => 'imageless-promotion',
    ]);
    $promotion->save();

    $payload = (new PromotionResource($promotion))->toArray(request());

    expect($payload['image_url'])->toBeNull()
        ->and($payload['starts_at'])->toBe('2026-05-01')
        ->and($payload['ends_at'])->toBe('2026-05-31');
});

/*
|--------------------------------------------------------------------------
| ReviewResource
|--------------------------------------------------------------------------
*/

it('derives the author name from the registered author when no guest name is stored', function (): void {
    $geography = contentResourcesGeography();
    $author = User::factory()->create(['name' => 'Registered Visitor']);

    $review = Review::create([
        'object_id' => $geography['object']->id,
        'rating' => 5,
        'body' => 'Wonderful stay, highly recommended.',
        'author_id' => $author->id,
        'author_name' => null,
        'status' => 'published',
        'created_at' => Carbon::parse('2026-02-10 12:00:00'),
    ]);

    $payload = (new ReviewResource($review))->toArray(request());

    expect($payload)->toBe([
        'id' => $review->id,
        'rating' => 5,
        'body' => 'Wonderful stay, highly recommended.',
        'author_name' => 'Registered Visitor',
        'date' => '2026-02-10',
        'owner_reply' => null,
        'owner_reply_date' => null,
    ]);
});

it('uses the stored guest name over the linked author when both are present', function (): void {
    $geography = contentResourcesGeography();
    $author = User::factory()->create(['name' => 'Should Not Be Used']);

    $review = Review::create([
        'object_id' => $geography['object']->id,
        'rating' => 4,
        'body' => 'Nice place, would come back.',
        'author_id' => $author->id,
        'author_name' => 'Jane Guest',
        'status' => 'published',
        'created_at' => Carbon::parse('2026-02-11 09:00:00'),
    ]);

    $payload = (new ReviewResource($review))->toArray(request());

    expect($payload['author_name'])->toBe('Jane Guest');
});

it('falls back to the anonymous translation when neither an author nor a guest name is stored', function (): void {
    $geography = contentResourcesGeography();

    $review = Review::create([
        'object_id' => $geography['object']->id,
        'rating' => 3,
        'body' => 'Average experience.',
        'author_id' => null,
        'author_name' => null,
        'status' => 'published',
        'created_at' => Carbon::parse('2026-02-12 08:00:00'),
    ]);

    $payload = (new ReviewResource($review))->toArray(request());

    expect($payload['author_name'])->toBe(__('public.object.reviews.anonymous'));
});

it('renders an owner reply and its date once the owner has replied', function (): void {
    $geography = contentResourcesGeography();

    $review = Review::create([
        'object_id' => $geography['object']->id,
        'rating' => 2,
        'body' => 'Disappointing.',
        'author_id' => null,
        'author_name' => 'Unhappy Guest',
        'status' => 'published',
        'owner_reply' => 'We are sorry to hear that — please reach out directly.',
        'owner_replied_at' => Carbon::parse('2026-02-13 15:30:00'),
        'created_at' => Carbon::parse('2026-02-12 08:00:00'),
    ]);

    $payload = (new ReviewResource($review))->toArray(request());

    expect($payload['owner_reply'])->toBe('We are sorry to hear that — please reach out directly.')
        ->and($payload['owner_reply_date'])->toBe('2026-02-13');
});
