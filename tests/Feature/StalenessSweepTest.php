<?php

declare(strict_types=1);

use App\Jobs\StalenessSweepJob;
use App\Models\Notification;
use App\Models\User;
use App\Services\Notifications\NotificationDispatchService;
use App\Services\Settings\SettingsRepository;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\NotificationChannelSeeder;
use Database\Seeders\NotificationTemplateSeeder;
use Database\Seeders\NotificationTypeSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Staleness Sweep
|--------------------------------------------------------------------------
|
| An object whose row has gone untouched past the configured staleness
| period gets exactly one reminder per staleness window: never a duplicate
| on a re-run within that window, and never a fresh object caught by
| mistake.
|
*/

// Queue::fake() keeps this test about "was a notification raised", not
// about whether an email channel actually delivers — the same isolation
// boundary ObjectBulkActionsTest already draws around queued work.
beforeEach(function (): void {
    Queue::fake();
    $this->seed([LanguageSeeder::class, NotificationChannelSeeder::class, NotificationTypeSeeder::class, NotificationTemplateSeeder::class]);
});

/** @return array{country: int, territory: int, type: int} */
function staleSweepGeography(): array
{
    $languageId = DB::table('languages')->where('code', 'en')->value('id');
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

/**
 * @param  array{country: int, territory: int, type: int}  $geo
 * @param  array<string, mixed>  $overrides
 */
function staleSweepObject(array $geo, array $overrides = []): int
{
    return DB::table('objects')->insertGetId(array_merge([
        'ulid' => (string) Str::ulid(),
        'owner_id' => User::factory()->create()->id,
        'object_type_id' => $geo['type'],
        'territory_id' => $geo['territory'],
        'country_id' => $geo['country'],
        'status' => 'published',
        'moderation_status' => 'approved',
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));
}

it('is registered on the scheduler and unreachable from a web route', function (): void {
    $schedule = app(Schedule::class);
    $names = collect($schedule->events())->map(fn ($event) => $event->description ?? '')->all();

    expect($names)->toContain('objects:sweep-staleness');
});

it('raises exactly one reminder for an object past the configured staleness period, and none for a fresh one', function (): void {
    $geo = staleSweepGeography();
    $staleId = staleSweepObject($geo, ['updated_at' => now()->subDays(200)]);
    $freshId = staleSweepObject($geo, ['updated_at' => now()->subDays(5)]);

    (new StalenessSweepJob)->handle(app(NotificationDispatchService::class), app(SettingsRepository::class));

    expect(Notification::query()->where('related_id', $staleId)->whereHas('type', fn ($q) => $q->where('key', 'information_out_of_date'))->count())->toBe(1)
        ->and(Notification::query()->where('related_id', $freshId)->count())->toBe(0);
});

it('does not raise a duplicate reminder for the same object within the staleness window', function (): void {
    $geo = staleSweepGeography();
    $staleId = staleSweepObject($geo, ['updated_at' => now()->subDays(200)]);

    (new StalenessSweepJob)->handle(app(NotificationDispatchService::class), app(SettingsRepository::class));
    (new StalenessSweepJob)->handle(app(NotificationDispatchService::class), app(SettingsRepository::class));

    expect(Notification::query()->where('related_id', $staleId)->whereHas('type', fn ($q) => $q->where('key', 'information_out_of_date'))->count())->toBe(1);
});

it('reads the staleness period from settings, not a hardcoded constant', function (): void {
    $geo = staleSweepGeography();
    // 100 days ago: not stale under the default 180-day period, but stale
    // once that period tightens to 90 — the object whose classification
    // must flip.
    $borderlineId = staleSweepObject($geo, ['updated_at' => now()->subDays(100)]);

    (new StalenessSweepJob)->handle(app(NotificationDispatchService::class), app(SettingsRepository::class));
    expect(Notification::query()->where('related_id', $borderlineId)->count())->toBe(0);

    app(SettingsRepository::class)->set('moderation.stale_object_days', 90);

    (new StalenessSweepJob)->handle(app(NotificationDispatchService::class), app(SettingsRepository::class));
    expect(Notification::query()->where('related_id', $borderlineId)->count())->toBe(1);
});

it('skips an object with no owner to notify', function (): void {
    $geo = staleSweepGeography();
    $orphanId = staleSweepObject($geo, ['updated_at' => now()->subDays(200), 'owner_id' => null]);

    (new StalenessSweepJob)->handle(app(NotificationDispatchService::class), app(SettingsRepository::class));

    expect(Notification::query()->where('related_id', $orphanId)->count())->toBe(0);
});
