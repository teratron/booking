<?php

declare(strict_types=1);

use App\Jobs\AnalyticsCompactionJob;
use App\Jobs\AnalyticsRollupJob;
use App\Models\Object_;
use App\Models\User;
use App\Services\Settings\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Analytics Rollup & Compaction
|--------------------------------------------------------------------------
|
| The two-tier model's second half: AnalyticsRollupJob compacts one day's
| raw stat_events into stat_dailies, idempotently; AnalyticsCompactionJob
| discards raw rows only once retention has passed AND that day's rollup
| exists. Both are scheduled jobs, never reachable from a web route.
|
*/

/** @return array{country: int, territory: int, type: int} */
function rollupFixtureGeography(): array
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

function rollupFixtureObject(int $countryId, int $territoryId, int $typeId): int
{
    $owner = User::factory()->create();

    return DB::table('objects')->insertGetId([
        'ulid' => (string) Str::ulid(), 'owner_id' => $owner->id,
        'object_type_id' => $typeId, 'territory_id' => $territoryId, 'country_id' => $countryId,
        'status' => 'published', 'moderation_status' => 'approved',
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function insertRawStatEvent(int $objectId, string $kind, string $occurredAt, int $territoryId, int $countryId): void
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

it('produces exactly one stat_dailies row per rollup key for the day', function (): void {
    $geo = rollupFixtureGeography();
    $objectId = rollupFixtureObject($geo['country'], $geo['territory'], $geo['type']);
    $date = '2026-08-01';

    // Three raw views of the same object on the same day collapse into
    // one aggregate row with count = 3.
    insertRawStatEvent($objectId, 'object_page_view', "{$date} 09:00:00", $geo['territory'], $geo['country']);
    insertRawStatEvent($objectId, 'object_page_view', "{$date} 12:00:00", $geo['territory'], $geo['country']);
    insertRawStatEvent($objectId, 'object_page_view', "{$date} 18:00:00", $geo['territory'], $geo['country']);
    // A different kind, same day, same object — a second, distinct row.
    insertRawStatEvent($objectId, 'photo_view', "{$date} 10:00:00", $geo['territory'], $geo['country']);
    // Outside the target day entirely — must not be folded in.
    insertRawStatEvent($objectId, 'object_page_view', '2026-08-02 09:00:00', $geo['territory'], $geo['country']);

    (new AnalyticsRollupJob($date))->handle();

    $rows = DB::table('stat_dailies')->where('date', $date)->get();
    expect($rows)->toHaveCount(2);

    $pageViewRow = $rows->firstWhere('kind', 'object_page_view');
    expect((int) $pageViewRow->count)->toBe(3);

    $photoViewRow = $rows->firstWhere('kind', 'photo_view');
    expect((int) $photoViewRow->count)->toBe(1);
});

it('is idempotent — running the rollup twice does not double the count', function (): void {
    $geo = rollupFixtureGeography();
    $objectId = rollupFixtureObject($geo['country'], $geo['territory'], $geo['type']);
    $date = '2026-08-01';

    insertRawStatEvent($objectId, 'object_page_view', "{$date} 09:00:00", $geo['territory'], $geo['country']);
    insertRawStatEvent($objectId, 'object_page_view', "{$date} 12:00:00", $geo['territory'], $geo['country']);

    (new AnalyticsRollupJob($date))->handle();
    (new AnalyticsRollupJob($date))->handle();

    expect(DB::table('stat_dailies')->where('date', $date)->count())->toBe(1);
    expect((int) DB::table('stat_dailies')->where('date', $date)->value('count'))->toBe(2);
});

it('leaves no partial stat_dailies row when the rollup fails partway through', function (): void {
    $geo = rollupFixtureGeography();
    $objectId = rollupFixtureObject($geo['country'], $geo['territory'], $geo['type']);
    $date = '2026-08-01';

    insertRawStatEvent($objectId, 'object_page_view', "{$date} 09:00:00", $geo['territory'], $geo['country']);
    insertRawStatEvent($objectId, 'object_page_view', "{$date} 12:00:00", $geo['territory'], $geo['country']);

    // A pre-existing row for an unrelated day, used below to prove the
    // failed run touched nothing outside its own target date either.
    DB::table('stat_dailies')->insert([
        'date' => '2026-01-01', 'subject_type' => Object_::class, 'subject_id' => $objectId,
        'kind' => 'object_page_view', 'locale' => 'en', 'count' => 5,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    // Injects a genuine failure into the real job's own insert step — not
    // a re-implementation of its transaction elsewhere — by throwing from
    // inside the query listener the moment the job's INSERT into
    // stat_dailies actually executes. The exception propagates out through
    // DB::transaction()'s closure exactly as a real failure would, so the
    // job's own rollback is what is under test.
    DB::listen(function ($query): void {
        if (str_contains($query->sql, 'insert into "stat_dailies"')) {
            throw new RuntimeException('Simulated mid-transaction failure.');
        }
    });

    expect(fn () => (new AnalyticsRollupJob($date))->handle())->toThrow(RuntimeException::class);

    DB::flushQueryLog();

    expect(DB::table('stat_dailies')->where('date', $date)->count())->toBe(0)
        ->and(DB::table('stat_dailies')->where('date', '2026-01-01')->count())->toBe(1);
});

it('compacts raw rows only after their day has been rolled up, never before', function (): void {
    app(SettingsRepository::class)->set('analytics.raw_retention_days', 90);

    $geo = rollupFixtureGeography();
    $objectId = rollupFixtureObject($geo['country'], $geo['territory'], $geo['type']);

    $rolledUpOldDate = now()->subDays(120)->toDateString();
    $notYetRolledUpOldDate = now()->subDays(110)->toDateString();
    $recentDate = now()->subDays(10)->toDateString();

    insertRawStatEvent($objectId, 'object_page_view', "{$rolledUpOldDate} 09:00:00", $geo['territory'], $geo['country']);
    insertRawStatEvent($objectId, 'object_page_view', "{$notYetRolledUpOldDate} 09:00:00", $geo['territory'], $geo['country']);
    insertRawStatEvent($objectId, 'object_page_view', "{$recentDate} 09:00:00", $geo['territory'], $geo['country']);

    // Only the first old date has actually been rolled up.
    (new AnalyticsRollupJob($rolledUpOldDate))->handle();
    $dailyRowsBefore = DB::table('stat_dailies')->count();

    (new AnalyticsCompactionJob)->handle(app(SettingsRepository::class));

    // The rolled-up-and-past-retention day's raw row is gone.
    expect(DB::table('stat_events')->whereDate('occurred_at', $rolledUpOldDate)->count())->toBe(0);

    // Past retention but never rolled up — must survive untouched.
    expect(DB::table('stat_events')->whereDate('occurred_at', $notYetRolledUpOldDate)->count())->toBe(1);

    // Within retention regardless of rollup state — must survive untouched.
    expect(DB::table('stat_events')->whereDate('occurred_at', $recentDate)->count())->toBe(1);

    // stat_dailies is untouched by compaction.
    expect(DB::table('stat_dailies')->count())->toBe($dailyRowsBefore);
});

it('registers both jobs on the schedule and neither is reachable from a web route', function (): void {
    Artisan::call('schedule:list');
    $output = Artisan::output();

    expect($output)->toContain('analytics:rollup')
        ->and($output)->toContain('analytics:compact');

    $routes = collect(app('router')->getRoutes())->map(fn ($route) => $route->getActionName());
    expect($routes->contains(fn (string $action): bool => str_contains($action, 'AnalyticsRollupJob')))->toBeFalse();
    expect($routes->contains(fn (string $action): bool => str_contains($action, 'AnalyticsCompactionJob')))->toBeFalse();
});
