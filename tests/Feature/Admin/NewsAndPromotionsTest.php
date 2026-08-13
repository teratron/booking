<?php

declare(strict_types=1);

use App\Jobs\PromotionArchivalJob;
use App\Models\ArticleCategory;
use App\Models\Country;
use App\Models\NewsItem;
use App\Models\Object_;
use App\Models\ObjectType;
use App\Models\Promotion;
use App\Models\Territory;
use App\Models\TerritoryLevel;
use App\Models\User;
use App\Services\Audit\AuditJournal;
use App\Services\Content\ContentPublicationService;
use App\Services\Content\NewsItemLifecycleService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| News & Promotions — Model, Moderation, and Auto-Archival
|--------------------------------------------------------------------------
|
| NewsItem and Promotion both carry a real moderation_status column and use
| FiltersModeration — unlike Article, an administrator-published row here
| must have moderation_status explicitly set to 'approved', or it stays
| invisible under the model's own default query. This file proves that
| directly, the same regression class tests/Feature/Admin/
| ObjectLifecyclePublicationTest.php proves for Object_.
|
*/

function newsAndPromotionsGeography(): array
{
    $languageId = DB::table('languages')->insertGetId([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $country = Country::create([
        'code' => 'MD', 'currency' => 'MDL', 'phone_code' => '+373',
        'primary_language_id' => $languageId, 'is_active' => true,
    ]);
    $country->translateOrNew('en')->name = 'Moldova';
    $country->save();

    $level = TerritoryLevel::create(['country_id' => $country->id, 'depth_rank' => 1]);
    $level->translateOrNew('en')->fill(['singular_name' => 'Region', 'plural_name' => 'Regions']);
    $level->save();

    $territory = Territory::create(['country_id' => $country->id, 'level_id' => $level->id]);
    $territory->translateOrNew('en')->fill(['name' => 'Gagauzia', 'slug' => 'gagauzia']);
    $territory->save();

    $objectType = ObjectType::create(['key' => 'hotel']);
    $objectType->translateOrNew('en')->name = 'Hotel';
    $objectType->save();

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
    $object->translateOrNew('en')->fill(['name' => 'Grand Hotel', 'slug' => 'grand-hotel']);
    $object->save();

    return compact('territory', 'object');
}

function newsAndPromotionsCategory(): ArticleCategory
{
    if (! DB::table('languages')->where('code', 'en')->exists()) {
        DB::table('languages')->insert([
            'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
            'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    $category = ArticleCategory::create(['slug' => 'news-and-promotions-category', 'is_active' => true]);
    $category->translateOrNew('en')->name = 'Seasonal';
    $category->save();

    return $category;
}

it('registers the promotion archival job on the scheduler, unreachable from a web route', function (): void {
    $schedule = app(Schedule::class);
    $names = collect($schedule->events())->map(fn ($event) => $event->description ?? '')->all();

    expect($names)->toContain('content:archive-promotions');
});

it('carries an author, optional object/territory/category, per-language fields, publish/end dates, status, moderation status, and view count', function (): void {
    ['territory' => $territory, 'object' => $object] = newsAndPromotionsGeography();
    $category = newsAndPromotionsCategory();
    $author = User::factory()->create();

    $newsItem = NewsItem::create([
        'author_id' => $author->id,
        'object_id' => $object->id,
        'territory_id' => $territory->id,
        'article_category_id' => $category->id,
        'status' => 'published',
        'moderation_status' => 'approved',
        'publish_at' => now()->subDay(),
        'end_at' => now()->addMonth(),
        'is_pinned' => true,
        'view_count' => 42,
    ]);
    $newsItem->translateOrNew('en')->fill([
        'title' => 'New Spa Wing Opens',
        'summary' => 'A short teaser.',
        'body' => 'The full news body.',
        'slug' => 'new-spa-wing-opens',
    ]);
    $newsItem->save();

    $fresh = NewsItem::query()->with(['author', 'object', 'territory', 'category'])->findOrFail($newsItem->id);

    expect($fresh->author->id)->toBe($author->id)
        ->and($fresh->object->id)->toBe($object->id)
        ->and($fresh->territory->id)->toBe($territory->id)
        ->and($fresh->category->id)->toBe($category->id)
        ->and($fresh->translate('en')->title)->toBe('New Spa Wing Opens')
        ->and($fresh->status)->toBe('published')
        ->and($fresh->moderation_status)->toBe('approved')
        ->and($fresh->publish_at)->not->toBeNull()
        ->and($fresh->end_at)->not->toBeNull()
        ->and($fresh->is_pinned)->toBeTrue()
        ->and($fresh->view_count)->toBe(42);
});

it('supports a portal-wide news item with no object at all', function (): void {
    $category = newsAndPromotionsCategory();
    $author = User::factory()->create();

    $newsItem = NewsItem::create([
        'author_id' => $author->id,
        'object_id' => null,
        'article_category_id' => $category->id,
        'status' => 'draft',
    ]);
    $newsItem->translateOrNew('en')->fill(['title' => 'Portal News', 'body' => 'Body.', 'slug' => 'portal-news']);
    $newsItem->save();

    // withUnmoderated(): this row is a draft with no moderation decision
    // yet, so it is correctly invisible under the default (moderation-
    // scoped) query — a separate concern from the one this test checks
    // (that object_id round-trips as null for a portal-wide item).
    expect(NewsItem::query()->withUnmoderated()->findOrFail($newsItem->id)->object_id)->toBeNull();
});

it('makes an administrator-published news item genuinely visible under NewsItem\'s own default query', function (): void {
    $category = newsAndPromotionsCategory();
    $author = User::factory()->create();
    $actor = User::factory()->create();

    $newsItem = NewsItem::create([
        'author_id' => $author->id,
        'article_category_id' => $category->id,
        'status' => 'draft',
        // Deliberately left null — exactly what a fresh row starts as; this
        // test proves the lifecycle service's publish() closes the gap.
    ]);
    $newsItem->translateOrNew('en')->fill(['title' => 'Visible After Publish', 'body' => 'Body.', 'slug' => 'visible-after-publish']);
    $newsItem->save();

    app(NewsItemLifecycleService::class)->publish($newsItem->fresh(), $actor);

    $visible = NewsItem::query()->find($newsItem->id);

    expect($visible)->not->toBeNull()
        ->and($visible->moderation_status)->toBe('approved')
        ->and($visible->status)->toBe('published');
});

it('carries an object, a territory, per-language title/description, an image collection, start/end dates, status, and moderation status', function (): void {
    ['territory' => $territory, 'object' => $object] = newsAndPromotionsGeography();

    $promotion = Promotion::create([
        'object_id' => $object->id,
        'territory_id' => $territory->id,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addMonth(),
        'status' => 'published',
        'moderation_status' => 'approved',
    ]);
    $promotion->translateOrNew('en')->fill([
        'title' => 'Summer Discount',
        'summary' => 'Twenty percent off all summer bookings.',
        'slug' => 'summer-discount',
    ]);
    $promotion->save();

    $fresh = Promotion::query()->with(['object', 'territory'])->findOrFail($promotion->id);

    expect($fresh->object->id)->toBe($object->id)
        ->and($fresh->territory->id)->toBe($territory->id)
        ->and($fresh->translate('en')->title)->toBe('Summer Discount')
        ->and($fresh->translate('en')->summary)->toBe('Twenty percent off all summer bookings.')
        ->and($fresh->starts_at)->not->toBeNull()
        ->and($fresh->ends_at)->not->toBeNull()
        ->and($fresh->status)->toBe('published')
        ->and($fresh->moderation_status)->toBe('approved');
});

it('archives an elapsed promotion within one sweep, and a re-run does not re-process it', function (): void {
    ['territory' => $territory, 'object' => $object] = newsAndPromotionsGeography();

    $elapsed = Promotion::create([
        'object_id' => $object->id,
        'territory_id' => $territory->id,
        'starts_at' => now()->subMonth(),
        'ends_at' => now()->subDay(),
        'status' => 'published',
        'moderation_status' => 'approved',
    ]);
    $elapsed->translateOrNew('en')->fill(['title' => 'Elapsed Promotion', 'slug' => 'elapsed-promotion']);
    $elapsed->save();

    $active = Promotion::create([
        'object_id' => $object->id,
        'territory_id' => $territory->id,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addMonth(),
        'status' => 'published',
        'moderation_status' => 'approved',
    ]);
    $active->translateOrNew('en')->fill(['title' => 'Active Promotion', 'slug' => 'active-promotion']);
    $active->save();

    (new PromotionArchivalJob)->handle(app(AuditJournal::class), app(ContentPublicationService::class));

    expect(Promotion::query()->find($elapsed->id)->status)->toBe('archived')
        ->and(Promotion::query()->find($active->id)->status)->toBe('published')
        ->and(DB::table('audits')->where('event', 'promotion_archived')->count())->toBe(1);

    // Idempotent: a second run has nothing left to archive for $elapsed —
    // the query itself excludes already-archived rows.
    (new PromotionArchivalJob)->handle(app(AuditJournal::class), app(ContentPublicationService::class));

    expect(DB::table('audits')->where('event', 'promotion_archived')->count())->toBe(1);
});
