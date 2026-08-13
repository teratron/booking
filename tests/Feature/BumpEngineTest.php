<?php

declare(strict_types=1);

use App\Exceptions\BumpRefusedException;
use App\Models\BumpEvent;
use App\Models\Object_;
use App\Models\Territory;
use App\Models\User;
use App\Services\Audit\AuditJournal;
use App\Services\Placement\BumpService;
use App\Services\Placement\PlacementOrderingService;
use App\Services\Settings\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Bump Engine
|--------------------------------------------------------------------------
|
| A bump moves an object to the first free position within its own tier,
| never above it — proven here against a sibling object in the same tier,
| not merely against a lower one. The free-bump limits (interval, per-period
| allowance) and the paid-bump price are read from the package, not a
| constant, and every field the specification names on the event record is
| asserted, not just the fact that a row exists.
|
*/

/** @return array{country: int, territory: int} */
function bumpFixtureGeography(): array
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

    return ['country' => $countryId, 'territory' => $territoryId];
}

function bumpFixtureObject(int $countryId, int $territoryId): int
{
    $typeId = DB::table('object_types')->insertGetId([
        'key' => 'hotel_'.Str::random(6), 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $owner = User::factory()->create();

    return DB::table('objects')->insertGetId([
        'ulid' => (string) Str::ulid(), 'owner_id' => $owner->id,
        'object_type_id' => $typeId, 'territory_id' => $territoryId, 'country_id' => $countryId,
        'status' => 'published', 'moderation_status' => 'approved',
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** @return array{tier: int, package: int} */
function bumpFixturePackage(int $rank, ?int $intervalHours = 24, ?int $freeBumpsPerPeriod = 1, float $paidPrice = 5.0): array
{
    $tierId = DB::table('placement_tiers')->insertGetId([
        'rank' => $rank, 'border_colour' => '#000000', 'badge_colour' => '#111111',
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $packageId = DB::table('placement_packages')->insertGetId([
        'placement_tier_id' => $tierId, 'price' => 10, 'currency' => 'EUR', 'validity_days' => 30,
        'bump_allowed' => true, 'bump_interval_hours' => $intervalHours,
        'free_bumps_per_period' => $freeBumpsPerPeriod, 'paid_bump_price' => $paidPrice,
        'is_active' => true, 'display_order' => $rank,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return ['tier' => $tierId, 'package' => $packageId];
}

function bumpFixturePlace(int $objectId, int $packageId): void
{
    DB::table('object_placements')->insert([
        'object_id' => $objectId, 'placement_package_id' => $packageId,
        'starts_at' => now()->subDays(2)->toDateString(), 'ends_at' => now()->addDays(28)->toDateString(),
        'created_at' => now()->subDays(2), 'updated_at' => now(),
    ]);
}

function bumpService(): BumpService
{
    return new BumpService(app(AuditJournal::class), new PlacementOrderingService(app(SettingsRepository::class)));
}

it('moves an object to the first position within its own tier for the given scope, never above the tier', function (): void {
    $geo = bumpFixtureGeography();
    $package = bumpFixturePackage(rank: 2);

    $bumped = bumpFixtureObject($geo['country'], $geo['territory']);
    $sibling = bumpFixtureObject($geo['country'], $geo['territory']);
    $higherTierObject = bumpFixtureObject($geo['country'], $geo['territory']);
    $higherTierPackage = bumpFixturePackage(rank: 1);

    bumpFixturePlace($bumped, $package['package']);
    bumpFixturePlace($sibling, $package['package']);
    bumpFixturePlace($higherTierObject, $higherTierPackage['package']);

    $actor = User::factory()->create();
    $territory = Territory::query()->findOrFail($geo['territory']);
    $object = Object_::query()->findOrFail($bumped);

    $event = bumpService()->bump($object, $territory, $actor, 'administrator', 'test bump');

    $orderedSameTier = Object_::query()
        ->whereHas('placement', fn ($q) => $q->whereHas('package', fn ($p) => $p->where('placement_tier_id', $package['tier'])))
        ->get()
        ->pipe(fn ($objects) => (new PlacementOrderingService(app(SettingsRepository::class)))
            ->apply($objects->toQuery(), $territory)
            ->pluck('id')
            ->all());

    expect($orderedSameTier)->toBe([$bumped, $sibling])
        ->and($event->new_position)->toBe(1)
        ->and($event->object_id)->toBe($bumped)
        ->and($event->placement_package_id)->toBe($package['package'])
        ->and($event->scope_type)->toBe(Territory::class)
        ->and($event->scope_id)->toBe($geo['territory'])
        ->and($event->type)->toBe('administrator')
        ->and($event->actor_id)->toBe($actor->id)
        ->and($event->comment)->toBe('test bump');

    // The bumped object still sorts behind the higher-tier one, regardless
    // of the bump — tier dominance survives the bump exactly as it must.
    $overall = (new PlacementOrderingService(app(SettingsRepository::class)))
        ->apply(Object_::query(), $territory)
        ->pluck('id')
        ->all();
    expect(array_search($higherTierObject, $overall, true))->toBeLessThan(array_search($bumped, $overall, true));
});

it('refuses a free bump before the package\'s minimum interval has elapsed', function (): void {
    $geo = bumpFixtureGeography();
    $package = bumpFixturePackage(rank: 2, intervalHours: 24, freeBumpsPerPeriod: 5);
    $objectId = bumpFixtureObject($geo['country'], $geo['territory']);
    bumpFixturePlace($objectId, $package['package']);

    $actor = User::factory()->create();
    $territory = Territory::query()->findOrFail($geo['territory']);
    $object = Object_::query()->findOrFail($objectId);

    bumpService()->bump($object, $territory, $actor, 'free');

    expect(fn () => bumpService()->bump($object->fresh(), $territory, $actor, 'free'))
        ->toThrow(BumpRefusedException::class);

    expect(BumpEvent::query()->where('object_id', $objectId)->count())->toBe(1);
});

it('refuses a free bump once the per-period allowance is exhausted, independent of the interval', function (): void {
    $geo = bumpFixtureGeography();
    // No interval limit, so only the per-period count gates this case.
    $package = bumpFixturePackage(rank: 2, intervalHours: null, freeBumpsPerPeriod: 2);
    $objectId = bumpFixtureObject($geo['country'], $geo['territory']);
    bumpFixturePlace($objectId, $package['package']);

    $actor = User::factory()->create();
    $territory = Territory::query()->findOrFail($geo['territory']);

    bumpService()->bump(Object_::query()->findOrFail($objectId), $territory, $actor, 'free');
    bumpService()->bump(Object_::query()->findOrFail($objectId), $territory, $actor, 'free');

    expect(fn () => bumpService()->bump(Object_::query()->findOrFail($objectId), $territory, $actor, 'free'))
        ->toThrow(BumpRefusedException::class);

    expect(BumpEvent::query()->where('object_id', $objectId)->where('type', 'free')->count())->toBe(2);
});

it('records the package\'s configured price on a paid bump and none on an administrator bump', function (): void {
    $geo = bumpFixtureGeography();
    $package = bumpFixturePackage(rank: 2, paidPrice: 7.5);
    $objectId = bumpFixtureObject($geo['country'], $geo['territory']);
    bumpFixturePlace($objectId, $package['package']);

    $actor = User::factory()->create();
    $territory = Territory::query()->findOrFail($geo['territory']);

    $paidEvent = bumpService()->bump(Object_::query()->findOrFail($objectId), $territory, $actor, 'paid');
    expect((float) $paidEvent->price)->toBe(7.5);

    $adminEvent = bumpService()->bump(Object_::query()->findOrFail($objectId), $territory, $actor, 'administrator');
    expect($adminEvent->price)->toBeNull();
});

it('refuses any bump when the current package does not allow bumping', function (): void {
    $geo = bumpFixtureGeography();
    $tierId = DB::table('placement_tiers')->insertGetId([
        'rank' => 3, 'border_colour' => '#000000', 'badge_colour' => '#111111',
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $packageId = DB::table('placement_packages')->insertGetId([
        'placement_tier_id' => $tierId, 'price' => 0, 'currency' => 'EUR', 'validity_days' => 365,
        'bump_allowed' => false, 'is_active' => true, 'display_order' => 3,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $objectId = bumpFixtureObject($geo['country'], $geo['territory']);
    bumpFixturePlace($objectId, $packageId);

    $actor = User::factory()->create();
    $territory = Territory::query()->findOrFail($geo['territory']);

    expect(fn () => bumpService()->bump(Object_::query()->findOrFail($objectId), $territory, $actor, 'administrator'))
        ->toThrow(BumpRefusedException::class);
});

it('journals an administrator-initiated bump distinctly from an automatic one', function (): void {
    $geo = bumpFixtureGeography();
    $package = bumpFixturePackage(rank: 2, intervalHours: null, freeBumpsPerPeriod: null);
    $objectId = bumpFixtureObject($geo['country'], $geo['territory']);
    bumpFixturePlace($objectId, $package['package']);

    $actor = User::factory()->create();
    $territory = Territory::query()->findOrFail($geo['territory']);

    bumpService()->bump(Object_::query()->findOrFail($objectId), $territory, $actor, 'administrator');
    bumpService()->bump(Object_::query()->findOrFail($objectId), $territory, $actor, 'automatic');

    $events = DB::table('audits')
        ->where('auditable_id', $objectId)
        ->where('event', 'object_bumped')
        ->orderBy('id')
        ->get();

    expect($events)->toHaveCount(2)
        ->and(json_decode((string) $events[0]->new_values, true)['type'])->toBe('administrator')
        ->and(json_decode((string) $events[1]->new_values, true)['type'])->toBe('automatic');
});
