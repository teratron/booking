<?php

declare(strict_types=1);

use App\Models\Banner;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Banner Counter Backfill
|--------------------------------------------------------------------------
|
| The one-time catch-up for any environment carrying banner traffic from
| before `banners.impressions`/`clicks` had a writer — everything the
| migration itself needs is already exercised by the ordinary suite (this
| migration ran, as a correct no-op, before every other test in this file
| ever executes), so this file re-runs it directly against a database that
| already holds historical `stat_dailies` rows, the one state a normal test
| run against an empty banners table never produces.
|
*/

function runBannerCounterBackfillMigration(): void
{
    /** @var Migration $migration */
    $migration = require database_path('migrations/2026_08_23_120000_backfill_banner_impression_and_click_counters.php');
    $migration->up();
}

it('backfills impressions and clicks from historical stat_dailies rows', function (): void {
    $slotId = DB::table('banner_slots')->insertGetId([
        'key' => 'backfill_probe', 'surfaces' => json_encode(['home']),
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $bannerId = DB::table('banners')->insertGetId([
        'banner_slot_id' => $slotId, 'name' => 'Backfill probe', 'advertiser' => 'Acme',
        'destination_link' => 'https://advertiser.example/landing',
        'starts_at' => now()->subMonth()->toDateString(), 'ends_at' => now()->addMonth()->toDateString(),
        'is_active' => true, 'impressions' => 0, 'clicks' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    // Two days of history, split across rows the way the real rollup job
    // would leave them — the backfill must sum across all of them, not just
    // read the latest.
    DB::table('stat_dailies')->insert([
        [
            'date' => now()->subDays(2)->toDateString(), 'subject_type' => Banner::class, 'subject_id' => $bannerId,
            'kind' => 'banner_impression', 'count' => 40, 'created_at' => now(), 'updated_at' => now(),
        ],
        [
            'date' => now()->subDay()->toDateString(), 'subject_type' => Banner::class, 'subject_id' => $bannerId,
            'kind' => 'banner_impression', 'count' => 60, 'created_at' => now(), 'updated_at' => now(),
        ],
        [
            'date' => now()->subDay()->toDateString(), 'subject_type' => Banner::class, 'subject_id' => $bannerId,
            'kind' => 'banner_click', 'count' => 7, 'created_at' => now(), 'updated_at' => now(),
        ],
    ]);

    runBannerCounterBackfillMigration();

    $banner = Banner::query()->findOrFail($bannerId);

    expect($banner->impressions)->toBe(100)
        ->and($banner->clicks)->toBe(7);
});

it('leaves a banner with no history at zero', function (): void {
    $slotId = DB::table('banner_slots')->insertGetId([
        'key' => 'backfill_empty_probe', 'surfaces' => json_encode(['home']),
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $bannerId = DB::table('banners')->insertGetId([
        'banner_slot_id' => $slotId, 'name' => 'No history', 'advertiser' => 'Acme',
        'destination_link' => 'https://advertiser.example/landing',
        'starts_at' => now()->subMonth()->toDateString(), 'ends_at' => now()->addMonth()->toDateString(),
        'is_active' => true, 'impressions' => 0, 'clicks' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    runBannerCounterBackfillMigration();

    $banner = Banner::query()->findOrFail($bannerId);

    expect($banner->impressions)->toBe(0)
        ->and($banner->clicks)->toBe(0);
});

it('never mixes another banner\'s history into the backfill', function (): void {
    $slotId = DB::table('banner_slots')->insertGetId([
        'key' => 'backfill_isolation_probe', 'surfaces' => json_encode(['home']),
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $targetId = DB::table('banners')->insertGetId([
        'banner_slot_id' => $slotId, 'name' => 'Target', 'advertiser' => 'Acme',
        'destination_link' => 'https://advertiser.example/target', 'display_order' => 0,
        'starts_at' => now()->subMonth()->toDateString(), 'ends_at' => now()->addMonth()->toDateString(),
        'is_active' => true, 'impressions' => 0, 'clicks' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $otherId = DB::table('banners')->insertGetId([
        'banner_slot_id' => $slotId, 'name' => 'Other', 'advertiser' => 'Acme',
        'destination_link' => 'https://advertiser.example/other', 'display_order' => 1,
        'starts_at' => now()->subMonth()->toDateString(), 'ends_at' => now()->addMonth()->toDateString(),
        'is_active' => true, 'impressions' => 0, 'clicks' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    DB::table('stat_dailies')->insert([
        'date' => now()->subDay()->toDateString(), 'subject_type' => Banner::class, 'subject_id' => $otherId,
        'kind' => 'banner_impression', 'count' => 999, 'created_at' => now(), 'updated_at' => now(),
    ]);

    runBannerCounterBackfillMigration();

    expect(Banner::query()->findOrFail($targetId)->impressions)->toBe(0)
        ->and(Banner::query()->findOrFail($otherId)->impressions)->toBe(999);
});
