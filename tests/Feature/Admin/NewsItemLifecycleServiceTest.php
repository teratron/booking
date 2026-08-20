<?php

declare(strict_types=1);

use App\Exceptions\ContentScheduleRefusedException;
use App\Models\NewsItem;
use App\Models\User;
use App\Services\Content\NewsItemLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OwenIt\Auditing\Models\Audit;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| News Item Lifecycle
|--------------------------------------------------------------------------
|
| The news item form's lifecycle actions beyond an ordinary field save:
| drafting, scheduling for a future publish date, immediate administrator
| publication (which must also clear the moderation gate every default
| query enforces), pinning, administrator-triggered early withdrawal ahead
| of the scheduled sweep, soft-delete ("archive"), and restore. Every
| transition writes its own action-journal entry.
|
*/

/** @return array{countryId: int, territoryId: int, typeId: int} */
function newsItemLifecycleGeography(): array
{
    $languageId = DB::table('languages')->insertGetId([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $countryId = DB::table('countries')->insertGetId([
        'code' => 'MD', 'currency' => 'MDL', 'phone_code' => '+373',
        'primary_language_id' => $languageId, 'is_active' => true, 'display_order' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $levelId = DB::table('territory_levels')->insertGetId([
        'country_id' => $countryId, 'depth_rank' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $territoryId = DB::table('territories')->insertGetId([
        'country_id' => $countryId, 'level_id' => $levelId, 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $typeId = DB::table('object_types')->insertGetId([
        'key' => 'accommodation', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return compact('countryId', 'territoryId', 'typeId');
}

/** @param  array{countryId: int, territoryId: int, typeId: int}  $geography */
function newsItemLifecycleObjectId(array $geography): int
{
    return DB::table('objects')->insertGetId([
        'ulid' => (string) Str::ulid(),
        'owner_id' => User::factory()->create()->id,
        'object_type_id' => $geography['typeId'],
        'territory_id' => $geography['territoryId'],
        'country_id' => $geography['countryId'],
        'status' => 'published',
        'moderation_status' => 'approved',
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

/**
 * @param  array{countryId: int, territoryId: int, typeId: int}  $geography
 * @param  array<string, mixed>  $overrides
 */
function newsItemLifecycleMake(array $geography, array $overrides = []): NewsItem
{
    $id = DB::table('news_items')->insertGetId(array_merge([
        'author_id' => User::factory()->create()->id,
        'object_id' => newsItemLifecycleObjectId($geography),
        'territory_id' => $geography['territoryId'],
        'status' => 'draft',
        'moderation_status' => null,
        'is_pinned' => false,
        'view_count' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ], $overrides));

    // withUnmoderated(): a freshly-created draft carries moderation_status
    // null, which ModerationScope's default query excludes just as it would
    // any other unapproved row — the same escape hatch this codebase's own
    // back-office queries already rely on to see their own pending content.
    /** @var NewsItem $newsItem */
    $newsItem = NewsItem::withUnmoderated()->findOrFail($id);

    return $newsItem;
}

function newsItemLifecycleLatestJournalEntry(string $event, int $newsItemId): ?Audit
{
    return Audit::query()
        ->where('event', $event)
        ->where('auditable_type', NewsItem::class)
        ->where('auditable_id', $newsItemId)
        ->latest('id')
        ->first();
}

it('saves a news item as a draft, clearing any publish date, and journals the transition', function (): void {
    $geography = newsItemLifecycleGeography();
    $newsItem = newsItemLifecycleMake($geography, ['status' => 'scheduled', 'publish_at' => now()->addDays(3)]);
    $actor = User::factory()->create();

    app(NewsItemLifecycleService::class)->saveAsDraft($newsItem, $actor);

    $fresh = $newsItem->fresh();
    expect($fresh->status)->toBe('draft')
        ->and($fresh->publish_at)->toBeNull();

    $entry = newsItemLifecycleLatestJournalEntry('news_item_saved_as_draft', $newsItem->id);
    expect($entry)->not->toBeNull()
        ->and($entry->old_values['status'])->toBe('scheduled')
        ->and($entry->new_values['status'])->toBe('draft');
});

it('schedules a news item for a future publish date and journals the transition', function (): void {
    $geography = newsItemLifecycleGeography();
    $newsItem = newsItemLifecycleMake($geography, ['status' => 'draft']);
    $actor = User::factory()->create();
    $publishAt = Carbon::now()->addDays(5);

    app(NewsItemLifecycleService::class)->schedule($newsItem, $publishAt, $actor);

    $fresh = $newsItem->fresh();
    expect($fresh->status)->toBe('scheduled')
        ->and($fresh->publish_at->isSameDay($publishAt))->toBeTrue();

    $entry = newsItemLifecycleLatestJournalEntry('news_item_scheduled', $newsItem->id);
    expect($entry)->not->toBeNull()
        ->and($entry->new_values['status'])->toBe('scheduled')
        ->and($entry->user_id)->toBe($actor->id);
});

it('refuses to schedule a news item for a publish date that is not in the future', function (): void {
    $geography = newsItemLifecycleGeography();
    $newsItem = newsItemLifecycleMake($geography, ['status' => 'draft']);
    $actor = User::factory()->create();

    app(NewsItemLifecycleService::class)->schedule($newsItem, Carbon::now()->subHour(), $actor);
})->throws(ContentScheduleRefusedException::class);

it('publishes a news item immediately, approving moderation so it clears the default query scope', function (): void {
    $geography = newsItemLifecycleGeography();
    $newsItem = newsItemLifecycleMake($geography, ['status' => 'draft', 'moderation_status' => null, 'publish_at' => null]);
    $actor = User::factory()->create();

    app(NewsItemLifecycleService::class)->publish($newsItem, $actor);

    $fresh = $newsItem->fresh();
    expect($fresh->status)->toBe('published')
        ->and($fresh->moderation_status)->toBe('approved')
        ->and($fresh->publish_at)->not->toBeNull()
        ->and($fresh->publish_at->isFuture())->toBeFalse();

    // The invariant this service's own docblock states explicitly: an
    // administrator-published item must actually be visible under the
    // model's default moderation-scoped query, not merely carry the right
    // status column.
    expect(NewsItem::query()->find($newsItem->id))->not->toBeNull();

    $entry = newsItemLifecycleLatestJournalEntry('news_item_published', $newsItem->id);
    expect($entry)->not->toBeNull()
        ->and($entry->new_values['moderation_status'])->toBe('approved');
});

it('corrects a future publish date to now when publishing a scheduled news item immediately', function (): void {
    $geography = newsItemLifecycleGeography();
    $newsItem = newsItemLifecycleMake($geography, [
        'status' => 'scheduled', 'moderation_status' => null, 'publish_at' => now()->addDays(4),
    ]);
    $actor = User::factory()->create();

    app(NewsItemLifecycleService::class)->publish($newsItem, $actor);

    expect($newsItem->fresh()->publish_at->isFuture())->toBeFalse();
});

it('leaves an already-past publish date untouched when publishing an already-current news item', function (): void {
    $geography = newsItemLifecycleGeography();
    $originalPublishAt = now()->subDays(2);
    $newsItem = newsItemLifecycleMake($geography, ['status' => 'draft', 'publish_at' => $originalPublishAt]);
    $actor = User::factory()->create();

    app(NewsItemLifecycleService::class)->publish($newsItem, $actor);

    expect($newsItem->fresh()->publish_at->toDateTimeString())->toBe($originalPublishAt->toDateTimeString());
});

it('pins and unpins a news item, journalling each transition independently', function (): void {
    $geography = newsItemLifecycleGeography();
    $newsItem = newsItemLifecycleMake($geography, ['status' => 'published', 'moderation_status' => 'approved']);
    $actor = User::factory()->create();

    app(NewsItemLifecycleService::class)->pin($newsItem, $actor);
    expect($newsItem->fresh()->is_pinned)->toBeTrue();
    expect(newsItemLifecycleLatestJournalEntry('news_item_pinned', $newsItem->id))->not->toBeNull();

    app(NewsItemLifecycleService::class)->unpin($newsItem, $actor);
    expect($newsItem->fresh()->is_pinned)->toBeFalse();
    expect(newsItemLifecycleLatestJournalEntry('news_item_unpinned', $newsItem->id))->not->toBeNull();
});

it('withdraws a published news item on administrator demand, ahead of its own end date', function (): void {
    $geography = newsItemLifecycleGeography();
    $newsItem = newsItemLifecycleMake($geography, [
        'status' => 'published', 'moderation_status' => 'approved', 'end_at' => now()->addDays(10),
    ]);
    $actor = User::factory()->create();

    app(NewsItemLifecycleService::class)->withdraw($newsItem, $actor);

    expect($newsItem->fresh()->status)->toBe('withdrawn');

    $entry = newsItemLifecycleLatestJournalEntry('news_item_withdrawn', $newsItem->id);
    expect($entry)->not->toBeNull()
        ->and($entry->old_values['status'])->toBe('published')
        ->and($entry->new_values['status'])->toBe('withdrawn');
});

it('archives (soft-deletes) a news item, journals it, and later restores it', function (): void {
    $geography = newsItemLifecycleGeography();
    $newsItem = newsItemLifecycleMake($geography, ['status' => 'withdrawn', 'moderation_status' => 'approved']);
    $actor = User::factory()->create();

    app(NewsItemLifecycleService::class)->archive($newsItem, $actor);

    expect(NewsItem::query()->find($newsItem->id))->toBeNull()
        ->and(NewsItem::withTrashed()->findOrFail($newsItem->id)->trashed())->toBeTrue();

    $archiveEntry = newsItemLifecycleLatestJournalEntry('news_item_archived', $newsItem->id);
    expect($archiveEntry)->not->toBeNull()
        ->and($archiveEntry->old_values['status'])->toBe('withdrawn');

    app(NewsItemLifecycleService::class)->restore(NewsItem::withUnmoderated()->withTrashed()->findOrFail($newsItem->id), $actor);

    expect(NewsItem::withUnmoderated()->withTrashed()->findOrFail($newsItem->id)->trashed())->toBeFalse();

    $restoreEntry = newsItemLifecycleLatestJournalEntry('news_item_restored', $newsItem->id);
    expect($restoreEntry)->not->toBeNull()
        ->and($restoreEntry->user_id)->toBe($actor->id);
});

it('invalidates content caches for a portal-wide news item with no attached object or territory', function (): void {
    $newsItem = new NewsItem;
    $newsItem->author_id = User::factory()->create()->id;
    $newsItem->object_id = null;
    $newsItem->territory_id = null;
    $newsItem->status = 'draft';
    $newsItem->is_pinned = false;
    $newsItem->view_count = 0;
    $newsItem->save();

    $actor = User::factory()->create();

    // The invalidation path branches on a null object/territory (see
    // NewsItemLifecycleService::invalidateContentCaches()) — a portal-wide
    // item (no object, no territory) must not throw reaching that branch.
    app(NewsItemLifecycleService::class)->publish($newsItem, $actor);

    expect($newsItem->fresh()->status)->toBe('published');
});
