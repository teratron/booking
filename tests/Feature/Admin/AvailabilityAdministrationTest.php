<?php

declare(strict_types=1);

use App\Exceptions\AvailabilityRevertRefusedException;
use App\Models\Object_;
use App\Models\User;
use App\Services\Objects\AvailabilityAdministrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Availability Administration
|--------------------------------------------------------------------------
|
| A narrow write path by design: an override writes both the object's own
| columns and an append-only history row in one transaction, never enqueues
| a moderation request, and invalidates the caches downstream pages will
| eventually read from. A revert is a status change like any other — it
| appends a further history row, it never deletes one.
|
*/

function availabilityGeography(): array
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
        'country_id' => $countryId, 'level_id' => $levelId, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $typeId = DB::table('object_types')->insertGetId([
        'key' => 'accommodation', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return compact('countryId', 'territoryId', 'typeId');
}

/** @param  array<string, mixed>  $overrides */
function availabilitySeedObject(array $geo, array $overrides = []): Object_
{
    $objectId = DB::table('objects')->insertGetId(array_merge([
        'ulid' => (string) Str::ulid(),
        'owner_id' => User::factory()->create()->id,
        'object_type_id' => $geo['typeId'],
        'territory_id' => $geo['territoryId'],
        'country_id' => $geo['countryId'],
        'status' => 'published',
        'availability_status' => 'available',
        'created_at' => now(), 'updated_at' => now(),
    ], $overrides));

    /** @var Object_ $object */
    $object = Object_::query()->withUnmoderated()->findOrFail($objectId);

    return $object;
}

it('overrides availability, writing both the object columns and an administrator-sourced history row in one write', function (): void {
    $geo = availabilityGeography();
    $object = availabilitySeedObject($geo);
    $actor = User::factory()->create();

    app(AvailabilityAdministrationService::class)->override($object, 'unavailable', $actor, 'Fully booked this week.');

    $object->refresh();

    expect($object->availability_status)->toBe('unavailable')
        ->and($object->availability_previous_status)->toBe('available')
        ->and($object->availability_changed_by)->toBe($actor->id)
        ->and($object->availability_changed_at)->not->toBeNull()
        ->and($object->availability_last_confirmed_at)->not->toBeNull()
        ->and($object->availability_comment)->toBe('Fully booked this week.');

    $history = DB::table('availability_histories')->where('object_id', $object->id)->get();

    expect($history)->toHaveCount(1)
        ->and($history->first()->from_status)->toBe('available')
        ->and($history->first()->to_status)->toBe('unavailable')
        ->and($history->first()->source)->toBe('administrator')
        ->and($history->first()->changed_by)->toBe($actor->id);
});

it('resets the staleness clock even when the chosen status matches the current one', function (): void {
    $geo = availabilityGeography();
    $object = availabilitySeedObject($geo, ['availability_last_confirmed_at' => now()->subDays(30)]);
    $actor = User::factory()->create();

    app(AvailabilityAdministrationService::class)->override($object, 'available', $actor);

    $object->refresh();

    expect($object->availability_status)->toBe('available')
        ->and($object->availability_last_confirmed_at->diffInSeconds(now()))->toBeLessThan(5);
});

it('reverts to the previous status, appending a further history row rather than deleting one', function (): void {
    $geo = availabilityGeography();
    $object = availabilitySeedObject($geo);
    $actor = User::factory()->create();

    app(AvailabilityAdministrationService::class)->override($object, 'unavailable', $actor);
    $object->refresh();
    app(AvailabilityAdministrationService::class)->revert($object, $actor);
    $object->refresh();

    expect($object->availability_status)->toBe('available')
        ->and($object->availability_previous_status)->toBe('unavailable');

    $history = DB::table('availability_histories')->where('object_id', $object->id)->orderBy('id')->get();

    expect($history)->toHaveCount(2)
        ->and($history->first()->to_status)->toBe('unavailable')
        ->and($history->last()->from_status)->toBe('unavailable')
        ->and($history->last()->to_status)->toBe('available');
});

it('refuses a revert when the object has never had a prior status recorded', function (): void {
    $geo = availabilityGeography();
    $object = availabilitySeedObject($geo);
    $actor = User::factory()->create();

    expect(fn () => app(AvailabilityAdministrationService::class)->revert($object, $actor))
        ->toThrow(AvailabilityRevertRefusedException::class);
});

it('never creates a moderation request as a side effect of an override', function (): void {
    $geo = availabilityGeography();
    $object = availabilitySeedObject($geo);
    $actor = User::factory()->create();

    app(AvailabilityAdministrationService::class)->override($object, 'unavailable', $actor);

    expect(DB::table('moderation_requests')->where('target_id', $object->id)->count())->toBe(0);
});

it('journals the override with the previous and new status', function (): void {
    $geo = availabilityGeography();
    $object = availabilitySeedObject($geo);
    $actor = User::factory()->create();

    app(AvailabilityAdministrationService::class)->override($object, 'unavailable', $actor);

    $audit = DB::table('audits')
        ->where('event', 'availability_overridden')
        ->where('auditable_id', $object->id)
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit->user_id)->toBe($actor->id)
        ->and(json_decode((string) $audit->old_values, true))->toBe(['availability_status' => 'available'])
        ->and(json_decode((string) $audit->new_values, true))->toBe(['availability_status' => 'unavailable']);
});

it('invalidates the catalog, territory, and object-page cache tags on a status write', function (): void {
    $geo = availabilityGeography();
    $object = availabilitySeedObject($geo);
    $actor = User::factory()->create();

    // Each future page caches under its own tag — a catalog listing under
    // 'catalog', a territory page under its own territory tag, an object
    // page under its own object tag. Three separate probes, one per tag,
    // is what a real flush touching all three needs to prove it reaches.
    Cache::tags(['catalog'])->put('probe', 'stale-catalog', 3600);
    Cache::tags(["territory:{$geo['territoryId']}"])->put('probe', 'stale-territory', 3600);
    Cache::tags(["object:{$object->id}"])->put('probe', 'stale-object', 3600);

    expect(Cache::tags(['catalog'])->get('probe'))->toBe('stale-catalog')
        ->and(Cache::tags(["territory:{$geo['territoryId']}"])->get('probe'))->toBe('stale-territory')
        ->and(Cache::tags(["object:{$object->id}"])->get('probe'))->toBe('stale-object');

    app(AvailabilityAdministrationService::class)->override($object, 'unavailable', $actor);

    expect(Cache::tags(['catalog'])->get('probe'))->toBeNull()
        ->and(Cache::tags(["territory:{$geo['territoryId']}"])->get('probe'))->toBeNull()
        ->and(Cache::tags(["object:{$object->id}"])->get('probe'))->toBeNull();
});
