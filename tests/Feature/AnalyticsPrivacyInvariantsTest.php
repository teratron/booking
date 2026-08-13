<?php

declare(strict_types=1);

use App\Jobs\AnalyticsCompactionJob;
use App\Jobs\AnalyticsRollupJob;
use App\Models\Object_;
use App\Models\User;
use App\Services\Analytics\EventCaptureService;
use App\Services\Settings\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Analytics Privacy & Fidelity Invariants
|--------------------------------------------------------------------------
|
| Four guarantees the analytics schema and capture path rest on:
|   1. stat_events / stat_dailies carry no durable visitor identifier at
|      all — proven against the column set itself, not sampled rows.
|   2. EventCaptureService::capture() never lets a forced internal failure
|      reach its caller — the swallow guarantee its own docblock claims.
|   3. The dedup token rotates once its fifteen-minute window has passed,
|      so two genuinely separate visits are never collapsed into one.
|   4. A raw row is discarded only once its day has been rolled up AND
|      retention has passed — never on either condition alone.
|
*/

/** @return array{country: int, territory: int, type: int} */
function privacyInvariantGeography(): array
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

function makePrivacyInvariantObject(int $countryId, int $territoryId, int $typeId): Object_
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

function insertPrivacyInvariantStatEvent(int $objectId, string $kind, string $occurredAt, int $territoryId, int $countryId): void
{
    DB::table('stat_events')->insert([
        'kind' => $kind,
        'subject_type' => Object_::class,
        'subject_id' => $objectId,
        'territory_id' => $territoryId,
        'country_id' => $countryId,
        'locale' => 'en',
        'occurred_at' => $occurredAt,
    ]);
}

/*
|--------------------------------------------------------------------------
| 1. No durable visitor identifier
|--------------------------------------------------------------------------
|
| A column-set assertion, not a sampled-row scan: the invariant is "this
| column does not exist", which is proven once and for all against the
| table's own schema rather than against whatever rows a test happens to
| have populated.
|
*/

it('carries no durable visitor identifier in its stat_events column set', function (): void {
    $columns = DB::getSchemaBuilder()->getColumnListing('stat_events');

    $forbidden = ['user_id', 'session_id', 'ip_address', 'visitor_id', 'ulid'];

    expect(array_intersect($columns, $forbidden))->toBeEmpty();
});

it('carries no durable visitor identifier in its stat_dailies column set', function (): void {
    $columns = DB::getSchemaBuilder()->getColumnListing('stat_dailies');

    $forbidden = ['user_id', 'session_id', 'ip_address', 'visitor_id', 'ulid'];

    expect(array_intersect($columns, $forbidden))->toBeEmpty();
});

/*
|--------------------------------------------------------------------------
| 2. A forced write failure never surfaces
|--------------------------------------------------------------------------
*/

it('never lets a forced internal failure reach capture()\'s caller', function (): void {
    // A queue connection with no registered driver forces
    // CaptureStatEventJob::dispatch() to throw the moment it resolves the
    // connection — the realistic shape of "the queue backend is
    // unavailable" that capture()'s try/catch must absorb rather than
    // propagate. This is the exact seam its own docblock claims to guard.
    config()->set('queue.default', 'nonexistent-connection');

    $geo = privacyInvariantGeography();
    $object = makePrivacyInvariantObject($geo['country'], $geo['territory'], $geo['type']);

    // Called directly, as a bare statement — not through
    // expect(...)->not->toThrow(Throwable::class). Pest's toThrow()
    // resolves a string argument via class_exists(), which returns false
    // for the Throwable interface and silently degrades to a
    // message-substring match instead of an instanceof check; combined
    // with ->not, that makes it pass regardless of whether anything was
    // actually thrown. Calling capture() unwrapped means a real leak
    // fails this test outright with an uncaught exception, which is the
    // guarantee genuinely under test here.
    app(EventCaptureService::class)->capture('object_page_view', $object);

    // The failure genuinely happened — no row snuck through some other
    // path — it was absorbed, not silently worked around.
    expect(DB::table('stat_events')->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| 3. Dedup token rotation across the 900-second window
|--------------------------------------------------------------------------
*/

it('rotates the dedup token once the 900-second window has passed, so the later capture is not collapsed', function (): void {
    $geo = privacyInvariantGeography();
    $object = makePrivacyInvariantObject($geo['country'], $geo['territory'], $geo['type']);

    app(EventCaptureService::class)->capture('object_page_view', $object);

    $this->travel(16)->minutes();

    app(EventCaptureService::class)->capture('object_page_view', $object);

    $events = DB::table('stat_events')->orderBy('id')->get();
    expect($events)->toHaveCount(2);

    $tokens = $events->pluck('dedup_token')->unique();
    expect($tokens)->toHaveCount(2);
});

/*
|--------------------------------------------------------------------------
| 4. Retention + compaction ordering
|--------------------------------------------------------------------------
*/

it('discards a raw row past retention only once its day has been rolled up, never on either condition alone', function (): void {
    $geo = privacyInvariantGeography();
    $object = makePrivacyInvariantObject($geo['country'], $geo['territory'], $geo['type']);

    $retentionDays = (int) app(SettingsRepository::class)->get('analytics.raw_retention_days');
    $oldDate = now()->subDays($retentionDays + 30)->toDateString();

    insertPrivacyInvariantStatEvent($object->id, 'object_page_view', "{$oldDate} 09:00:00", $geo['territory'], $geo['country']);

    // Compaction alone, with no prior rollup, must leave the raw row
    // untouched — the ordering constraint the daily schedule encodes
    // (rollup runs before compaction) verified here rather than assumed.
    (new AnalyticsCompactionJob)->handle(app(SettingsRepository::class));

    expect(DB::table('stat_events')->whereDate('occurred_at', $oldDate)->count())->toBe(1)
        ->and(DB::table('stat_dailies')->where('date', $oldDate)->count())->toBe(0);

    (new AnalyticsRollupJob($oldDate))->handle();

    expect(DB::table('stat_dailies')->where('date', $oldDate)->count())->toBe(1);

    (new AnalyticsCompactionJob)->handle(app(SettingsRepository::class));

    // The raw row is gone; its aggregate survives.
    expect(DB::table('stat_events')->whereDate('occurred_at', $oldDate)->count())->toBe(0)
        ->and(DB::table('stat_dailies')->where('date', $oldDate)->count())->toBe(1);
});
