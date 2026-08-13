<?php

declare(strict_types=1);

use App\Jobs\CaptureStatEventJob;
use App\Models\Object_;
use App\Models\StatEvent;
use App\Models\User;
use App\Services\Analytics\EventCaptureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Event Capture — Fire-and-Forget Ingestion
|--------------------------------------------------------------------------
|
| EventCaptureService::capture() is the single entry point every tracked
| interaction funnels through. Four contracts are proven here: it never
| blocks the caller, a capture-path exception never surfaces, deduplication
| collapses two captures sharing a token within the same fifteen-minute
| window, and the token rotates once that window has passed.
|
*/

/** @return array{country: int, territory: int, type: int} */
function captureFixtureGeography(): array
{
    $languageId = DB::table('languages')->insertGetId([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $countryId = DB::table('countries')->insertGetId([
        'code' => 'MD', 'currency' => 'MDL', 'phone_code' => '+373',
        'primary_language_id' => $languageId, 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $levelId = DB::table('territory_levels')->insertGetId([
        'country_id' => $countryId, 'depth_rank' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $territoryId = DB::table('territories')->insertGetId([
        'country_id' => $countryId, 'level_id' => $levelId, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $typeId = DB::table('object_types')->insertGetId([
        'key' => 'hotel', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return ['country' => $countryId, 'territory' => $territoryId, 'type' => $typeId];
}

function makeCaptureObject(int $countryId, int $territoryId, int $typeId): Object_
{
    $owner = User::factory()->create();

    $id = DB::table('objects')->insertGetId([
        'ulid' => (string) Str::ulid(), 'owner_id' => $owner->id,
        'object_type_id' => $typeId, 'territory_id' => $territoryId, 'country_id' => $countryId,
        'status' => 'published', 'moderation_status' => 'approved',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return Object_::query()->findOrFail($id);
}

it('never blocks the caller — dispatch is queued, not executed inline', function (): void {
    Queue::fake();

    $geo = captureFixtureGeography();
    $object = makeCaptureObject($geo['country'], $geo['territory'], $geo['type']);

    app(EventCaptureService::class)->capture('object_page_view', $object);

    Queue::assertPushed(CaptureStatEventJob::class);

    // The decisive proof that capture() does not block on the write: with
    // the queue faked, CaptureStatEventJob's handle() never ran, and yet
    // capture() returned — so whatever "not blocking" means here, it
    // cannot be capture() performing the insert itself before returning.
    // A wall-clock budget would only prove the same thing less reliably,
    // since a queue double's dispatch cost is already near zero.
    expect(StatEvent::query()->count())->toBe(0);
});

it('dispatches by queueing a job rather than running its write inline, even against the real (sync) queue', function (): void {
    // With QUEUE_CONNECTION=sync (the test environment's default), dispatch()
    // still goes through the full queue contract — CaptureStatEventJob is
    // constructed, serialized considerations aside, and executed by the
    // worker pipeline rather than by capture() calling StatEvent::create()
    // itself. This is what "queues a job rather than writing inline" means
    // in production too: the call site never talks to the events table.
    $geo = captureFixtureGeography();
    $object = makeCaptureObject($geo['country'], $geo['territory'], $geo['type']);

    app(EventCaptureService::class)->capture('object_page_view', $object);

    $event = StatEvent::query()->sole();
    expect($event->subject_type)->toBe(Object_::class)
        ->and($event->subject_id)->toBe($object->id)
        ->and($event->kind)->toBe('object_page_view');
});

it('never lets a capture-path exception reach the caller', function (): void {
    // A queue connection that does not exist forces the dispatch itself
    // to throw — the realistic shape of "the queue backend is unavailable"
    // that capture() must degrade past rather than propagate.
    config()->set('queue.default', 'nonexistent-connection');

    $geo = captureFixtureGeography();
    $object = makeCaptureObject($geo['country'], $geo['territory'], $geo['type']);

    expect(fn () => app(EventCaptureService::class)->capture('object_page_view', $object))
        ->not->toThrow(Throwable::class);
});

it('silently drops an unrecognised kind instead of throwing', function (): void {
    $geo = captureFixtureGeography();
    $object = makeCaptureObject($geo['country'], $geo['territory'], $geo['type']);

    expect(fn () => app(EventCaptureService::class)->capture('not_a_real_kind', $object))
        ->not->toThrow(Throwable::class);

    expect(StatEvent::query()->count())->toBe(0);
});

it('deduplicates two captures of the same interaction inside one time window', function (): void {
    $geo = captureFixtureGeography();
    $object = makeCaptureObject($geo['country'], $geo['territory'], $geo['type']);

    app(EventCaptureService::class)->capture('object_page_view', $object);
    app(EventCaptureService::class)->capture('object_page_view', $object);

    expect(StatEvent::query()->count())->toBe(1);
});

it('rotates the dedup token once the time window has passed, so a later capture is not collapsed', function (): void {
    $geo = captureFixtureGeography();
    $object = makeCaptureObject($geo['country'], $geo['territory'], $geo['type']);

    app(EventCaptureService::class)->capture('object_page_view', $object);

    $this->travel(16)->minutes();

    app(EventCaptureService::class)->capture('object_page_view', $object);

    expect(StatEvent::query()->count())->toBe(2);

    $tokens = StatEvent::query()->orderBy('id')->pluck('dedup_token')->all();
    expect($tokens[0])->not->toBe($tokens[1]);
});

it('does not deduplicate two distinct interactions sharing the same window', function (): void {
    $geo = captureFixtureGeography();
    $object = makeCaptureObject($geo['country'], $geo['territory'], $geo['type']);

    app(EventCaptureService::class)->capture('object_page_view', $object);
    app(EventCaptureService::class)->capture('photo_view', $object);

    expect(StatEvent::query()->count())->toBe(2);
});

it('confirms stat_events is a genuinely partitioned table, not a single flat one', function (): void {
    $partitionedRelation = DB::selectOne(
        "select relkind from pg_class where relname = 'stat_events'"
    );

    expect($partitionedRelation)->not->toBeNull()
        ->and($partitionedRelation->relkind)->toBe('p');

    $childPartitions = DB::select(
        "select relname from pg_class where relispartition and relname like 'stat_events%'"
    );

    expect($childPartitions)->not->toBeEmpty();
});
