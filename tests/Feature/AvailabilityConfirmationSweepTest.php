<?php

declare(strict_types=1);

use App\Jobs\AvailabilityConfirmationSweepJob;
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
| Availability Confirmation Sweep
|--------------------------------------------------------------------------
|
| The reminder respects the configured cadence, applies only to object
| types that actually track availability, and never repeats within the
| same cadence window — distinct from SweepStaleAvailabilityJob, which
| optionally auto-resets rather than reminds.
|
*/

beforeEach(function (): void {
    Queue::fake();
    $this->seed([LanguageSeeder::class, NotificationChannelSeeder::class, NotificationTypeSeeder::class, NotificationTemplateSeeder::class]);
});

/** @return array{country: int, territory: int, trackedType: int, untrackedType: int} */
function confirmSweepGeography(): array
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
    $trackedType = DB::table('object_types')->insertGetId([
        'key' => 'hotel', 'is_active' => true, 'has_availability_status' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $untrackedType = DB::table('object_types')->insertGetId([
        'key' => 'restaurant', 'is_active' => true, 'has_availability_status' => false,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return ['country' => $countryId, 'territory' => $territoryId, 'trackedType' => $trackedType, 'untrackedType' => $untrackedType];
}

/**
 * @param  array{country: int, territory: int, trackedType: int, untrackedType: int}  $geo
 * @param  array<string, mixed>  $overrides
 */
function confirmSweepObject(array $geo, array $overrides = []): int
{
    return DB::table('objects')->insertGetId(array_merge([
        'ulid' => (string) Str::ulid(),
        'owner_id' => User::factory()->create()->id,
        'object_type_id' => $geo['trackedType'],
        'territory_id' => $geo['territory'],
        'country_id' => $geo['country'],
        'status' => 'published',
        'moderation_status' => 'approved',
        'availability_status' => 'available',
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));
}

it('is registered on the scheduler and unreachable from a web route', function (): void {
    $schedule = app(Schedule::class);
    $names = collect($schedule->events())->map(fn ($event) => $event->description ?? '')->all();

    expect($names)->toContain('availability:sweep-confirmation');
});

it('reminds the owner of a tracked object unconfirmed past the cadence, including one never confirmed, and skips a fresh one', function (): void {
    $geo = confirmSweepGeography();
    $staleId = confirmSweepObject($geo, ['availability_last_confirmed_at' => now()->subDays(20)]);
    $neverConfirmedId = confirmSweepObject($geo, ['availability_last_confirmed_at' => null]);
    $freshId = confirmSweepObject($geo, ['availability_last_confirmed_at' => now()->subDays(2)]);

    (new AvailabilityConfirmationSweepJob)->handle(app(NotificationDispatchService::class), app(SettingsRepository::class));

    expect(Notification::query()->where('related_id', $staleId)->whereHas('type', fn ($q) => $q->where('key', 'confirm_availability_status'))->count())->toBe(1)
        ->and(Notification::query()->where('related_id', $neverConfirmedId)->whereHas('type', fn ($q) => $q->where('key', 'confirm_availability_status'))->count())->toBe(1)
        ->and(Notification::query()->where('related_id', $freshId)->count())->toBe(0);
});

it('never fires for an object type that does not track availability', function (): void {
    $geo = confirmSweepGeography();
    $untrackedId = confirmSweepObject($geo, [
        'object_type_id' => $geo['untrackedType'],
        'availability_last_confirmed_at' => now()->subDays(60),
    ]);

    (new AvailabilityConfirmationSweepJob)->handle(app(NotificationDispatchService::class), app(SettingsRepository::class));

    expect(Notification::query()->where('related_id', $untrackedId)->count())->toBe(0);
});

it('does not raise a duplicate reminder for the same object within the confirmation cadence', function (): void {
    $geo = confirmSweepGeography();
    $staleId = confirmSweepObject($geo, ['availability_last_confirmed_at' => now()->subDays(20)]);

    (new AvailabilityConfirmationSweepJob)->handle(app(NotificationDispatchService::class), app(SettingsRepository::class));
    (new AvailabilityConfirmationSweepJob)->handle(app(NotificationDispatchService::class), app(SettingsRepository::class));

    expect(Notification::query()->where('related_id', $staleId)->whereHas('type', fn ($q) => $q->where('key', 'confirm_availability_status'))->count())->toBe(1);
});

it('reads the confirmation cadence from settings, not a hardcoded constant', function (): void {
    $geo = confirmSweepGeography();
    // 10 days ago: not due under the default 14-day cadence, due once it
    // tightens to 7 — the object whose classification must flip.
    $borderlineId = confirmSweepObject($geo, ['availability_last_confirmed_at' => now()->subDays(10)]);

    (new AvailabilityConfirmationSweepJob)->handle(app(NotificationDispatchService::class), app(SettingsRepository::class));
    expect(Notification::query()->where('related_id', $borderlineId)->count())->toBe(0);

    app(SettingsRepository::class)->set('availability.confirmation_period_days', 7);

    (new AvailabilityConfirmationSweepJob)->handle(app(NotificationDispatchService::class), app(SettingsRepository::class));
    expect(Notification::query()->where('related_id', $borderlineId)->count())->toBe(1);
});
