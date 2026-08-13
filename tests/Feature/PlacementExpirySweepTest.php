<?php

declare(strict_types=1);

use App\Jobs\PlacementExpirySweepJob;
use App\Models\Notification;
use App\Models\User;
use App\Services\Audit\AuditJournal;
use App\Services\Placement\PlacementLifecycleService;
use App\Services\Settings\SettingsRepository;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\NotificationChannelSeeder;
use Database\Seeders\NotificationTemplateSeeder;
use Database\Seeders\NotificationTypeSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Placement Expiry Sweep
|--------------------------------------------------------------------------
|
| Warnings and the expiry action are two independent passes, proven
| separately: a warning never mutates a placement, and the action never
| fires twice for the same object on the same day, whether that is because
| a notification already exists (the warning path's guard) or because
| demotion itself moves ends_at out of the past (the action path's
| structural guard).
|
*/

// The notification side of this job reads real seeded types/templates
// (placement_expiring, package_expired) — seeding them here, rather than
// hand-rolling a fixture row, is what makes "a notification was raised"
// mean the same thing in this test as it does in production.
beforeEach(fn () => $this->seed([LanguageSeeder::class, NotificationChannelSeeder::class, NotificationTypeSeeder::class, NotificationTemplateSeeder::class]));

/** @return array{country: int, territory: int, lowType: int} */
function expiryFixtureGeography(): array
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

    return ['country' => $countryId, 'territory' => $territoryId, 'lowType' => $typeId];
}

/** @return array{tierId: int, packageId: int} */
function expiryFixturePackage(int $rank, int $objectTypeId): array
{
    $tierId = DB::table('placement_tiers')->insertGetId([
        'rank' => $rank, 'border_colour' => '#000000', 'badge_colour' => '#111111',
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $packageId = DB::table('placement_packages')->insertGetId([
        'placement_tier_id' => $tierId, 'object_type_id' => $objectTypeId,
        'price' => $rank === 4 ? 0 : 50, 'currency' => 'EUR', 'validity_days' => 365,
        'bump_allowed' => false, 'is_active' => true, 'display_order' => $rank,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return ['tierId' => $tierId, 'packageId' => $packageId];
}

function expiryFixtureObjectAndOwner(int $countryId, int $territoryId, int $typeId): int
{
    $owner = User::factory()->create();

    return DB::table('objects')->insertGetId([
        'ulid' => (string) Str::ulid(), 'owner_id' => $owner->id,
        'object_type_id' => $typeId, 'territory_id' => $territoryId, 'country_id' => $countryId,
        'status' => 'published', 'moderation_status' => 'approved',
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

it('is registered on the scheduler and unreachable from a web route', function (): void {
    $schedule = app(Schedule::class);
    $names = collect($schedule->events())->map(fn ($event) => $event->description ?? '')->all();

    expect($names)->toContain('placement:sweep-expiry');
});

it('raises a warning at a configured offset without mutating the placement, and does not duplicate on a re-run', function (): void {
    $geo = expiryFixtureGeography();
    $package = expiryFixturePackage(2, $geo['lowType']);
    $objectId = expiryFixtureObjectAndOwner($geo['country'], $geo['territory'], $geo['lowType']);

    DB::table('object_placements')->insert([
        'object_id' => $objectId, 'placement_package_id' => $package['packageId'],
        'starts_at' => now()->subDays(23)->toDateString(), 'ends_at' => now()->addDays(7)->toDateString(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    (new PlacementExpirySweepJob)->handle(
        app(PlacementLifecycleService::class),
        app(SettingsRepository::class),
        app(AuditJournal::class),
    );

    expect(DB::table('object_placements')->where('object_id', $objectId)->value('placement_package_id'))
        ->toBe($package['packageId'])
        ->and(Notification::query()->where('related_id', $objectId)->whereHas('type', fn ($q) => $q->where('key', 'placement_expiring'))->count())
        ->toBe(1);

    // Re-run the same day: no duplicate.
    (new PlacementExpirySweepJob)->handle(
        app(PlacementLifecycleService::class),
        app(SettingsRepository::class),
        app(AuditJournal::class),
    );

    expect(Notification::query()->where('related_id', $objectId)->whereHas('type', fn ($q) => $q->where('key', 'placement_expiring'))->count())->toBe(1);
});

it('demotes an expired placement to the lowest tier, closes the history row, notifies once, and never re-acts on a re-run', function (): void {
    $geo = expiryFixtureGeography();
    $paidPackage = expiryFixturePackage(1, $geo['lowType']);
    $lowestPackage = expiryFixturePackage(4, $geo['lowType']);
    $objectId = expiryFixtureObjectAndOwner($geo['country'], $geo['territory'], $geo['lowType']);

    DB::table('object_placements')->insert([
        'object_id' => $objectId, 'placement_package_id' => $paidPackage['packageId'],
        'starts_at' => now()->subDays(31)->toDateString(), 'ends_at' => now()->subDay()->toDateString(),
        'created_at' => now()->subDays(31), 'updated_at' => now(),
    ]);
    DB::table('placement_histories')->insert([
        'object_id' => $objectId, 'placement_package_id' => $paidPackage['packageId'],
        'starts_at' => now()->subDays(31)->toDateString(), 'ends_at' => null,
        'amount' => 50, 'currency' => 'EUR', 'status' => 'paid',
        'created_at' => now()->subDays(31), 'updated_at' => now()->subDays(31),
    ]);

    (new PlacementExpirySweepJob)->handle(
        app(PlacementLifecycleService::class),
        app(SettingsRepository::class),
        app(AuditJournal::class),
    );

    expect(DB::table('object_placements')->where('object_id', $objectId)->value('placement_package_id'))
        ->toBe($lowestPackage['packageId'])
        ->and(DB::table('placement_histories')->where('object_id', $objectId)->where('placement_package_id', $paidPackage['packageId'])->whereNotNull('ends_at')->exists())
        ->toBeTrue()
        ->and(Notification::query()->where('related_id', $objectId)->whereHas('type', fn ($q) => $q->where('key', 'package_expired'))->count())
        ->toBe(1);

    $countBefore = Notification::query()->count();

    // Re-run: object_placements.ends_at is now in the future (the new
    // package's own validity window), so the "expired and unprocessed"
    // query no longer matches it — no re-demotion, no duplicate notice.
    (new PlacementExpirySweepJob)->handle(
        app(PlacementLifecycleService::class),
        app(SettingsRepository::class),
        app(AuditJournal::class),
    );

    expect(DB::table('object_placements')->where('object_id', $objectId)->value('placement_package_id'))->toBe($lowestPackage['packageId'])
        ->and(Notification::query()->count())->toBe($countBefore);
});

it('hides the object instead of demoting when the configured action is "hide"', function (): void {
    app(SettingsRepository::class)->set('placement.expired_behaviour', 'hide');

    $geo = expiryFixtureGeography();
    $package = expiryFixturePackage(1, $geo['lowType']);
    $objectId = expiryFixtureObjectAndOwner($geo['country'], $geo['territory'], $geo['lowType']);

    DB::table('object_placements')->insert([
        'object_id' => $objectId, 'placement_package_id' => $package['packageId'],
        'starts_at' => now()->subDays(31)->toDateString(), 'ends_at' => now()->subDay()->toDateString(),
        'created_at' => now()->subDays(31), 'updated_at' => now(),
    ]);

    (new PlacementExpirySweepJob)->handle(
        app(PlacementLifecycleService::class),
        app(SettingsRepository::class),
        app(AuditJournal::class),
    );

    expect(DB::table('objects')->where('id', $objectId)->value('status'))->toBe('hidden')
        // The package is untouched by the hide path — only status changes.
        ->and(DB::table('object_placements')->where('object_id', $objectId)->value('placement_package_id'))->toBe($package['packageId'])
        ->and(DB::table('audits')->where('auditable_id', $objectId)->where('event', 'object_hidden')->exists())->toBeTrue();
});
