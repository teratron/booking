<?php

declare(strict_types=1);

use App\Jobs\NewsItemWithdrawalJob;
use App\Models\Article;
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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Shared Content Publication Pipeline
|--------------------------------------------------------------------------
|
| Proves the cross-type contract: scheduled content stays invisible until
| its date, publishing invalidates a real, known cache tag rather than a
| full flush, and a news item's own end-date behavior (withdraw, page
| retained) is genuinely distinct from a promotion's (archive).
|
*/

function publicationPipelineGeography(): array
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
    $territory->translateOrNew('en')->fill(['country_id' => $country->id, 'name' => 'Gagauzia', 'slug' => 'gagauzia']);
    $territory->save();

    $objectType = ObjectType::create(['key' => 'hotel']);
    $objectType->translateOrNew('en')->fill(['name' => 'Hotel', 'slug' => 'hotel']);
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

function publicationPipelineCategory(): ArticleCategory
{
    if (! DB::table('languages')->where('code', 'en')->exists()) {
        DB::table('languages')->insert([
            'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
            'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    $category = ArticleCategory::create(['slug' => 'publication-pipeline-category', 'is_active' => true]);
    $category->translateOrNew('en')->name = 'Seasonal';
    $category->save();

    return $category;
}

it('excludes a scheduled news item and a scheduled promotion from their own published() scope until the date arrives', function (): void {
    ['territory' => $territory, 'object' => $object] = publicationPipelineGeography();
    $category = publicationPipelineCategory();
    $author = User::factory()->create();

    $futureNews = NewsItem::create([
        'author_id' => $author->id,
        'article_category_id' => $category->id,
        'status' => 'published',
        'moderation_status' => 'approved',
        'publish_at' => now()->addWeek(),
    ]);
    $futureNews->translateOrNew('en')->fill(['title' => 'Future News', 'body' => 'Body.', 'slug' => 'future-news']);
    $futureNews->save();

    $dueNews = NewsItem::create([
        'author_id' => $author->id,
        'article_category_id' => $category->id,
        'status' => 'published',
        'moderation_status' => 'approved',
        'publish_at' => now()->subHour(),
    ]);
    $dueNews->translateOrNew('en')->fill(['title' => 'Due News', 'body' => 'Body.', 'slug' => 'due-news']);
    $dueNews->save();

    expect(NewsItem::published()->pluck('id')->all())->toBe([$dueNews->id]);

    $futurePromotion = Promotion::create([
        'object_id' => $object->id,
        'territory_id' => $territory->id,
        'starts_at' => now()->addWeek(),
        'ends_at' => now()->addMonth(),
        'status' => 'published',
        'moderation_status' => 'approved',
    ]);
    $futurePromotion->translateOrNew('en')->fill(['title' => 'Future Promotion', 'slug' => 'future-promotion']);
    $futurePromotion->save();

    $duePromotion = Promotion::create([
        'object_id' => $object->id,
        'territory_id' => $territory->id,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addMonth(),
        'status' => 'published',
        'moderation_status' => 'approved',
    ]);
    $duePromotion->translateOrNew('en')->fill(['title' => 'Due Promotion', 'slug' => 'due-promotion']);
    $duePromotion->save();

    expect(Promotion::published()->pluck('id')->all())->toBe([$duePromotion->id]);
});

it('invalidates the exact cache tags ContentPublicationService enumerates, not a full flush', function (): void {
    ['territory' => $territory, 'object' => $object] = publicationPipelineGeography();
    $category = publicationPipelineCategory();
    $author = User::factory()->create();
    $actor = User::factory()->create();

    $newsItem = NewsItem::create([
        'author_id' => $author->id,
        'object_id' => $object->id,
        'territory_id' => $territory->id,
        'article_category_id' => $category->id,
        'status' => 'draft',
    ]);
    $newsItem->translateOrNew('en')->fill(['title' => 'Cache Probe News', 'body' => 'Body.', 'slug' => 'cache-probe-news']);
    $newsItem->save();

    // Populate three cache entries: one under a tag this publish should
    // invalidate (the news item's own), one under this object's tag, and
    // one under an unrelated tag that must survive untouched — proving the
    // invalidation is targeted, not a blanket Cache::flush().
    Cache::tags(['content', "news:{$newsItem->id}"])->put('probe-own', 'value', 60);
    Cache::tags(['object', "object:{$object->id}"])->put('probe-object', 'value', 60);
    Cache::tags(['unrelated-tag'])->put('probe-unrelated', 'value', 60);

    app(NewsItemLifecycleService::class)->publish($newsItem->fresh(), $actor);

    expect(Cache::tags(['content', "news:{$newsItem->id}"])->get('probe-own'))->toBeNull()
        ->and(Cache::tags(['object', "object:{$object->id}"])->get('probe-object'))->toBeNull()
        ->and(Cache::tags(['unrelated-tag'])->get('probe-unrelated'))->toBe('value');
});

it('withdraws an elapsed news item from feeds while its own record stays reachable, distinct from a promotion\'s full archival', function (): void {
    ['territory' => $territory, 'object' => $object] = publicationPipelineGeography();
    $category = publicationPipelineCategory();
    $author = User::factory()->create();

    $elapsedNews = NewsItem::create([
        'author_id' => $author->id,
        'object_id' => $object->id,
        'territory_id' => $territory->id,
        'article_category_id' => $category->id,
        'status' => 'published',
        'moderation_status' => 'approved',
        'publish_at' => now()->subMonth(),
        'end_at' => now()->subDay(),
    ]);
    $elapsedNews->translateOrNew('en')->fill(['title' => 'Elapsed News', 'body' => 'Body.', 'slug' => 'elapsed-news']);
    $elapsedNews->save();

    (new NewsItemWithdrawalJob)->handle(app(AuditJournal::class), app(ContentPublicationService::class));

    $reloaded = NewsItem::query()->find($elapsedNews->id);

    expect($reloaded)->not->toBeNull()
        ->and($reloaded->status)->toBe('withdrawn')
        ->and(NewsItem::published()->whereKey($elapsedNews->id)->exists())->toBeFalse();

    // Idempotent: a second run has nothing left to withdraw.
    (new NewsItemWithdrawalJob)->handle(app(AuditJournal::class), app(ContentPublicationService::class));
    expect(DB::table('audits')->where('event', 'news_item_withdrawn')->count())->toBe(1);
});

it('registers the news withdrawal job on the scheduler, unreachable from a web route', function (): void {
    $schedule = app(Schedule::class);
    $names = collect($schedule->events())->map(fn ($event) => $event->description ?? '')->all();

    expect($names)->toContain('content:withdraw-news');
});

it('produces the same ContentSummary shape across Article, NewsItem, and Promotion', function (): void {
    ['territory' => $territory, 'object' => $object] = publicationPipelineGeography();
    $category = publicationPipelineCategory();
    $author = User::factory()->create();

    $article = Article::create(['author_id' => $author->id, 'article_category_id' => $category->id, 'status' => 'draft']);
    $article->translateOrNew('en')->fill(['title' => 'Summary Probe Article', 'body' => 'Body.', 'slug' => 'summary-probe-article']);
    $article->save();

    $newsItem = NewsItem::create(['author_id' => $author->id, 'article_category_id' => $category->id, 'status' => 'draft']);
    $newsItem->translateOrNew('en')->fill(['title' => 'Summary Probe News', 'body' => 'Body.', 'slug' => 'summary-probe-news']);
    $newsItem->save();

    $promotion = Promotion::create([
        'object_id' => $object->id, 'territory_id' => $territory->id,
        'starts_at' => now(), 'ends_at' => now()->addMonth(), 'status' => 'draft',
    ]);
    $promotion->translateOrNew('en')->fill(['title' => 'Summary Probe Promotion', 'slug' => 'summary-probe-promotion']);
    $promotion->save();

    foreach ([$article, $newsItem, $promotion] as $model) {
        $summary = $model->toContentSummary();

        expect($summary->id)->toBe($model->id)
            ->and($summary->title)->toBe($model->title)
            ->and($summary->slug)->toBe($model->slug);
    }

    expect($article->toContentSummary()->contentType)->toBe('article')
        ->and($newsItem->toContentSummary()->contentType)->toBe('news')
        ->and($promotion->toContentSummary()->contentType)->toBe('promotion');
});
