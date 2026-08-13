<?php

declare(strict_types=1);

use App\Filament\Cabinet\Pages\Dashboard;
use App\Filament\Cabinet\Resources\Objects\Pages\ListObjects;
use App\Models\Object_;
use App\Models\User;
use App\Services\Cabinet\AvailabilityToggleService;
use App\Services\Moderation\ModerationModeResolver;
use App\Services\Moderation\ModerationPipeline;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Cabinet Availability Toggle
|--------------------------------------------------------------------------
|
| The owner's one-tap availability switch — a write path deliberately kept
| separate from the general object-edit flow. A tap writes the object's own
| columns and an append-only history row in one transaction, resets the
| staleness clock, invalidates the caches downstream pages read from, and
| never touches moderation. It also never reaches beyond the `availability_*`
| columns: placement, catalog ordering, and contact-channel visibility must
| read identically before and after.
|
*/

/** @return array{countryId: int, territoryId: int, typeId: int} */
function cabinetAvailabilityToggleGeography(): array
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
        'key' => 'accommodation', 'is_active' => true, 'has_rooms' => false, 'has_availability_status' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return compact('countryId', 'territoryId', 'typeId');
}

/**
 * @param  array{countryId: int, territoryId: int, typeId: int}  $geo
 * @param  array<string, mixed>  $overrides
 */
function cabinetAvailabilityToggleMakeObject(array $geo, int $ownerId, array $overrides = []): Object_
{
    $objectId = DB::table('objects')->insertGetId(array_merge([
        'ulid' => (string) Str::ulid(),
        'owner_id' => $ownerId,
        'object_type_id' => $geo['typeId'],
        'territory_id' => $geo['territoryId'],
        'country_id' => $geo['countryId'],
        'status' => 'published',
        'moderation_status' => 'approved',
        'availability_status' => 'available',
        'created_at' => now(), 'updated_at' => now(),
    ], $overrides));

    DB::table('object_translations')->insert([
        'object_id' => $objectId, 'locale' => 'en', 'name' => 'Seaside Villa',
        'slug' => 'seaside-villa-'.$objectId, 'needs_review' => false, 'published_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    /** @var Object_ $object */
    $object = Object_::query()->withUnmoderated()->findOrFail($objectId);

    return $object;
}

/**
 * A placement grant plus its tier — planted so a test can prove the toggle
 * never touches either row.
 *
 * @return array{tierId: int, packageId: int, placementId: int}
 */
function cabinetAvailabilityTogglePlacement(Object_ $object, int $typeId): array
{
    $tierId = DB::table('placement_tiers')->insertGetId([
        'rank' => 1, 'border_colour' => '#000000', 'badge_colour' => '#000000',
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $packageId = DB::table('placement_packages')->insertGetId([
        'placement_tier_id' => $tierId, 'object_type_id' => $typeId,
        'price' => 10.00, 'currency' => 'EUR', 'validity_days' => 30,
        'bump_allowed' => false, 'is_active' => true, 'display_order' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $placementId = DB::table('object_placements')->insertGetId([
        'object_id' => $object->id, 'placement_package_id' => $packageId,
        'starts_at' => now()->subDays(1)->toDateString(), 'ends_at' => now()->addDays(30)->toDateString(),
        'internal_priority' => 7, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return compact('tierId', 'packageId', 'placementId');
}

function cabinetAvailabilityToggleContactChannel(Object_ $object): int
{
    $typeId = DB::table('contact_channel_types')->insertOrIgnore([
        'id' => 501, 'key' => 'phone', 'is_active' => true, 'display_order' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]) ? 501 : (int) DB::table('contact_channel_types')->where('key', 'phone')->value('id');

    return DB::table('contact_channels')->insertGetId([
        'object_id' => $object->id, 'contact_channel_type_id' => $typeId,
        'raw_value' => '+37360000000', 'display_order' => 1, 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function cabinetAvailabilityToggleOwner(string $roleKey): User
{
    foreach (['object.view', 'object.edit', 'cabinet_access'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $role = Role::findOrCreate($roleKey, 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $role->syncPermissions(['object.view', 'object.edit', 'cabinet_access']);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user->fresh();
}

it('flips available to unavailable, writing the object columns and an owner-sourced history row in one write', function (): void {
    $geo = cabinetAvailabilityToggleGeography();
    $owner = cabinetAvailabilityToggleOwner('toggle_owner_flip_off');
    $object = cabinetAvailabilityToggleMakeObject($geo, $owner->id);

    app(AvailabilityToggleService::class)->toggle($object, $owner);

    $object->refresh();

    expect($object->availability_status)->toBe('unavailable')
        ->and($object->availability_previous_status)->toBe('available')
        ->and($object->availability_changed_by)->toBe($owner->id)
        ->and($object->availability_changed_at)->not->toBeNull()
        ->and($object->availability_last_confirmed_at)->not->toBeNull()
        ->and($object->availability_last_confirmed_at->diffInSeconds(now()))->toBeLessThan(5);

    $history = DB::table('availability_histories')->where('object_id', $object->id)->get();

    expect($history)->toHaveCount(1)
        ->and($history->first()->from_status)->toBe('available')
        ->and($history->first()->to_status)->toBe('unavailable')
        ->and($history->first()->source)->toBe('owner')
        ->and($history->first()->changed_by)->toBe($owner->id);
});

it('flips unavailable back to available on a second tap, appending a further history row', function (): void {
    $geo = cabinetAvailabilityToggleGeography();
    $owner = cabinetAvailabilityToggleOwner('toggle_owner_flip_back');
    $object = cabinetAvailabilityToggleMakeObject($geo, $owner->id, ['availability_status' => 'unavailable']);

    app(AvailabilityToggleService::class)->toggle($object, $owner);
    $object->refresh();

    expect($object->availability_status)->toBe('available')
        ->and($object->availability_previous_status)->toBe('unavailable');

    $history = DB::table('availability_histories')->where('object_id', $object->id)->orderBy('id')->get();

    expect($history)->toHaveCount(1)
        ->and($history->first()->from_status)->toBe('unavailable')
        ->and($history->first()->to_status)->toBe('available');
});

it('treats unspecified as toggling to available — the same default a freshly onboarded object starts from', function (): void {
    $geo = cabinetAvailabilityToggleGeography();
    $owner = cabinetAvailabilityToggleOwner('toggle_owner_unspecified');
    $object = cabinetAvailabilityToggleMakeObject($geo, $owner->id, ['availability_status' => 'unspecified']);

    app(AvailabilityToggleService::class)->toggle($object, $owner);
    $object->refresh();

    expect($object->availability_status)->toBe('available')
        ->and($object->availability_previous_status)->toBe('unspecified');
});

it('resets the staleness clock on every tap, even one made shortly after the last confirmation', function (): void {
    $geo = cabinetAvailabilityToggleGeography();
    $owner = cabinetAvailabilityToggleOwner('toggle_owner_staleness');
    $object = cabinetAvailabilityToggleMakeObject($geo, $owner->id, [
        'availability_last_confirmed_at' => now()->subDays(20),
    ]);

    app(AvailabilityToggleService::class)->toggle($object, $owner);
    $object->refresh();

    expect($object->availability_last_confirmed_at->diffInSeconds(now()))->toBeLessThan(5);
});

it('never creates a moderation request as a side effect of a toggle', function (): void {
    $geo = cabinetAvailabilityToggleGeography();
    $owner = cabinetAvailabilityToggleOwner('toggle_owner_no_moderation_request');
    $object = cabinetAvailabilityToggleMakeObject($geo, $owner->id);

    app(AvailabilityToggleService::class)->toggle($object, $owner);

    expect(DB::table('moderation_requests')->where('target_id', $object->id)->count())->toBe(0);
});

it('never invokes the moderation pipeline or resolves a moderation mode as a side effect of a toggle', function (): void {
    $geo = cabinetAvailabilityToggleGeography();
    $owner = cabinetAvailabilityToggleOwner('toggle_owner_no_pipeline_call');
    $object = cabinetAvailabilityToggleMakeObject($geo, $owner->id);

    // Both collaborators are `final` (this codebase's own convention for
    // services), which rules out a Mockery class-name spy — Mockery cannot
    // generate a subclass proxy of a final class. Rebinding the container
    // slot to a factory that throws is the falsification this warrants
    // instead: it proves the negative directly against the real resolution
    // path (any `app(ModerationPipeline::class)` or constructor injection
    // reached from the toggle would blow up here), not merely against the
    // toggle service's own source not mentioning either class by name.
    app()->bind(ModerationPipeline::class, function (): never {
        throw new RuntimeException('ModerationPipeline must never be resolved from an availability toggle.');
    });
    app()->bind(ModerationModeResolver::class, function (): never {
        throw new RuntimeException('ModerationModeResolver must never be resolved from an availability toggle.');
    });

    app(AvailabilityToggleService::class)->toggle($object, $owner);

    expect(DB::table('moderation_requests')->where('target_id', $object->id)->count())->toBe(0);
});

it('invalidates the catalog, territory, and object-page cache tags on a toggle', function (): void {
    $geo = cabinetAvailabilityToggleGeography();
    $owner = cabinetAvailabilityToggleOwner('toggle_owner_cache');
    $object = cabinetAvailabilityToggleMakeObject($geo, $owner->id);

    Cache::tags(['catalog'])->put('probe', 'stale-catalog', 3600);
    Cache::tags(["territory:{$geo['territoryId']}"])->put('probe', 'stale-territory', 3600);
    Cache::tags(["object:{$object->id}"])->put('probe', 'stale-object', 3600);

    expect(Cache::tags(['catalog'])->get('probe'))->toBe('stale-catalog')
        ->and(Cache::tags(["territory:{$geo['territoryId']}"])->get('probe'))->toBe('stale-territory')
        ->and(Cache::tags(["object:{$object->id}"])->get('probe'))->toBe('stale-object');

    app(AvailabilityToggleService::class)->toggle($object, $owner);

    expect(Cache::tags(['catalog'])->get('probe'))->toBeNull()
        ->and(Cache::tags(["territory:{$geo['territoryId']}"])->get('probe'))->toBeNull()
        ->and(Cache::tags(["object:{$object->id}"])->get('probe'))->toBeNull();
});

it('leaves placement tier, catalog ordering, and contact-channel visibility exactly as they were', function (): void {
    $geo = cabinetAvailabilityToggleGeography();
    $owner = cabinetAvailabilityToggleOwner('toggle_owner_scope');
    $object = cabinetAvailabilityToggleMakeObject($geo, $owner->id);
    $placement = cabinetAvailabilityTogglePlacement($object, $geo['typeId']);
    $contactChannelId = cabinetAvailabilityToggleContactChannel($object);

    $placementBefore = DB::table('object_placements')->where('id', $placement['placementId'])->first();
    $tierBefore = DB::table('placement_tiers')->where('id', $placement['tierId'])->first();
    $contactChannelBefore = DB::table('contact_channels')->where('id', $contactChannelId)->first();
    $publicationStatusBefore = $object->status;
    $moderationStatusBefore = $object->moderation_status;

    app(AvailabilityToggleService::class)->toggle($object, $owner);

    $placementAfter = DB::table('object_placements')->where('id', $placement['placementId'])->first();
    $tierAfter = DB::table('placement_tiers')->where('id', $placement['tierId'])->first();
    $contactChannelAfter = DB::table('contact_channels')->where('id', $contactChannelId)->first();
    $object->refresh();

    expect($placementAfter->placement_package_id)->toBe($placementBefore->placement_package_id)
        ->and($placementAfter->internal_priority)->toBe($placementBefore->internal_priority)
        ->and($placementAfter->pinned_position)->toBe($placementBefore->pinned_position)
        ->and($tierAfter->rank)->toBe($tierBefore->rank)
        ->and($contactChannelAfter->is_active)->toBe($contactChannelBefore->is_active)
        ->and($contactChannelAfter->raw_value)->toBe($contactChannelBefore->raw_value)
        ->and($object->status)->toBe($publicationStatusBefore)
        ->and($object->moderation_status)->toBe($moderationStatusBefore);
});

it('is reachable from the cabinet dashboard header and performs the write when tapped', function (): void {
    $geo = cabinetAvailabilityToggleGeography();
    $owner = cabinetAvailabilityToggleOwner('toggle_owner_dashboard_reach');
    $object = cabinetAvailabilityToggleMakeObject($geo, $owner->id);

    $response = $this->actingAs($owner)
        ->followingRedirects()
        ->get('/'.config('booking.panels.cabinet.path'));

    $response->assertSuccessful()->assertSee(__('panel.cabinet.availability.mark_unavailable'));

    Filament::setCurrentPanel('cabinet');
    Filament::setTenant($object, isQuiet: true);

    Livewire::actingAs($owner)
        ->test(Dashboard::class)
        ->callAction('toggle_availability');

    expect($object->refresh()->availability_status)->toBe('unavailable');
});

it('is reachable from the owner object list as a compact row action and performs the write when tapped', function (): void {
    $geo = cabinetAvailabilityToggleGeography();
    $owner = cabinetAvailabilityToggleOwner('toggle_owner_list_reach');
    $object = cabinetAvailabilityToggleMakeObject($geo, $owner->id);

    Filament::setCurrentPanel('cabinet');
    Filament::setTenant($object, isQuiet: true);

    Livewire::actingAs($owner)
        ->test(ListObjects::class)
        ->assertTableActionExists('toggle_availability')
        ->callTableAction('toggle_availability', $object);

    expect($object->refresh()->availability_status)->toBe('unavailable');
});
