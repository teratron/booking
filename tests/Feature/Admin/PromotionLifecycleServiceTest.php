<?php

declare(strict_types=1);

use App\Exceptions\ContentScheduleRefusedException;
use App\Models\Promotion;
use App\Models\User;
use App\Services\Content\PromotionLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OwenIt\Auditing\Models\Audit;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Promotion Lifecycle
|--------------------------------------------------------------------------
|
| The promotion form's lifecycle actions beyond an ordinary field save:
| scheduling for a future start, immediate publication, administrator-
| triggered early archival ahead of the scheduled sweep, soft-delete, and
| restore. Every transition writes its own action-journal entry naming the
| status it moved from and to.
|
*/

/** @return array{countryId: int, territoryId: int, typeId: int} */
function promotionLifecycleGeography(): array
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
function promotionLifecycleObjectId(array $geography): int
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
function promotionLifecycleMake(array $geography, array $overrides = []): Promotion
{
    $id = DB::table('promotions')->insertGetId(array_merge([
        'object_id' => promotionLifecycleObjectId($geography),
        'territory_id' => $geography['territoryId'],
        'starts_at' => now()->toDateString(),
        'ends_at' => now()->addDays(30)->toDateString(),
        'status' => 'draft',
        'moderation_status' => null,
        'created_at' => now(), 'updated_at' => now(),
    ], $overrides));

    // withUnmoderated(): a freshly-created draft carries moderation_status
    // null, which ModerationScope's default query excludes just as it would
    // any other unapproved row — the same escape hatch this codebase's own
    // back-office queries already rely on to see their own pending content.
    /** @var Promotion $promotion */
    $promotion = Promotion::withUnmoderated()->findOrFail($id);

    return $promotion;
}

function promotionLifecycleLatestJournalEntry(string $event, int $promotionId): ?Audit
{
    return Audit::query()
        ->where('event', $event)
        ->where('auditable_type', Promotion::class)
        ->where('auditable_id', $promotionId)
        ->latest('id')
        ->first();
}

it('schedules a promotion for a future start date and journals the transition', function (): void {
    $geography = promotionLifecycleGeography();
    $promotion = promotionLifecycleMake($geography, ['status' => 'draft']);
    $actor = User::factory()->create();
    $startsAt = Carbon::now()->addDays(5)->startOfDay();

    app(PromotionLifecycleService::class)->schedule($promotion, $startsAt, $actor);

    $fresh = $promotion->fresh();
    expect($fresh->status)->toBe('scheduled')
        ->and($fresh->starts_at->isSameDay($startsAt))->toBeTrue();

    $entry = promotionLifecycleLatestJournalEntry('promotion_scheduled', $promotion->id);
    expect($entry)->not->toBeNull()
        ->and($entry->old_values['status'])->toBe('draft')
        ->and($entry->new_values['status'])->toBe('scheduled')
        ->and($entry->user_id)->toBe($actor->id);
});

it('refuses to schedule a promotion for a start date that is not in the future', function (): void {
    $geography = promotionLifecycleGeography();
    $promotion = promotionLifecycleMake($geography, ['status' => 'draft']);
    $actor = User::factory()->create();

    app(PromotionLifecycleService::class)->schedule($promotion, Carbon::now()->subDay(), $actor);
})->throws(ContentScheduleRefusedException::class);

it('publishes a promotion immediately, approving moderation and correcting a future start date to now', function (): void {
    $geography = promotionLifecycleGeography();
    $promotion = promotionLifecycleMake($geography, [
        'status' => 'scheduled',
        'moderation_status' => null,
        'starts_at' => now()->addDays(3)->toDateString(),
    ]);
    $actor = User::factory()->create();

    app(PromotionLifecycleService::class)->publish($promotion, $actor);

    $fresh = $promotion->fresh();
    expect($fresh->status)->toBe('published')
        ->and($fresh->moderation_status)->toBe('approved')
        ->and($fresh->starts_at->isFuture())->toBeFalse();

    $entry = promotionLifecycleLatestJournalEntry('promotion_published', $promotion->id);
    expect($entry)->not->toBeNull()
        ->and($entry->old_values['status'])->toBe('scheduled')
        ->and($entry->new_values['moderation_status'])->toBe('approved');
});

it('leaves a past start date untouched when publishing an already-current promotion', function (): void {
    $geography = promotionLifecycleGeography();
    $originalStart = now()->subDays(2)->toDateString();
    $promotion = promotionLifecycleMake($geography, ['status' => 'draft', 'starts_at' => $originalStart]);
    $actor = User::factory()->create();

    app(PromotionLifecycleService::class)->publish($promotion, $actor);

    expect($promotion->fresh()->starts_at->toDateString())->toBe($originalStart);
});

it('archives a promotion on administrator demand, ahead of its own end date, and journals it', function (): void {
    $geography = promotionLifecycleGeography();
    $promotion = promotionLifecycleMake($geography, [
        'status' => 'published', 'moderation_status' => 'approved',
        'ends_at' => now()->addDays(20)->toDateString(),
    ]);
    $actor = User::factory()->create();

    app(PromotionLifecycleService::class)->archive($promotion, $actor);

    expect($promotion->fresh()->status)->toBe('archived');

    $entry = promotionLifecycleLatestJournalEntry('promotion_archived', $promotion->id);
    expect($entry)->not->toBeNull()
        ->and($entry->old_values['status'])->toBe('published')
        ->and($entry->new_values['status'])->toBe('archived');
});

it('soft-deletes a promotion, journals it, and later restores it', function (): void {
    $geography = promotionLifecycleGeography();
    $promotion = promotionLifecycleMake($geography, ['status' => 'published', 'moderation_status' => 'approved']);
    $actor = User::factory()->create();

    app(PromotionLifecycleService::class)->delete($promotion, $actor);

    expect(Promotion::query()->find($promotion->id))->toBeNull()
        ->and(Promotion::withTrashed()->findOrFail($promotion->id)->trashed())->toBeTrue();

    $deleteEntry = promotionLifecycleLatestJournalEntry('promotion_deleted', $promotion->id);
    expect($deleteEntry)->not->toBeNull()
        ->and($deleteEntry->old_values['status'])->toBe('published');

    app(PromotionLifecycleService::class)->restore(Promotion::withUnmoderated()->withTrashed()->findOrFail($promotion->id), $actor);

    expect(Promotion::withUnmoderated()->withTrashed()->findOrFail($promotion->id)->trashed())->toBeFalse();

    $restoreEntry = promotionLifecycleLatestJournalEntry('promotion_restored', $promotion->id);
    expect($restoreEntry)->not->toBeNull()
        ->and($restoreEntry->user_id)->toBe($actor->id);
});
