<?php

declare(strict_types=1);

use App\Filament\Admin\Resources\Objects\Tables\ObjectsTable;
use App\Jobs\SweepStaleAvailabilityJob;
use App\Models\Object_;
use App\Models\User;
use App\Services\Objects\AvailabilityAdministrationService;
use App\Services\Objects\ObjectBulkActionService;
use App\Services\Settings\SettingsRepository;
use Filament\Tables\Filters\Filter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Availability Staleness
|--------------------------------------------------------------------------
|
| The confirmation cadence is a setting an administrator can change, never
| a constant; the object list offers every quick filter §5.5 names; a bulk
| reset over the stale ones journals one history row per affected object;
| and the optional auto-reset sweep — off by default, since resetting a
| stale "no vacancies" back to "available" manufactures exactly the false
| claim this whole feature exists to prevent — only ever runs as a
| scheduled job, never from a web route.
|
*/

function stalenessGeography(): array
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
function stalenessSeedObject(array $geo, array $overrides = []): Object_
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

function stalenessActor(): User
{
    foreach (['admin_panel_access', 'object.view', 'object.edit'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $role = Role::findOrCreate('staleness_admin', 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $role->syncPermissions(['admin_panel_access', 'object.view', 'object.edit']);

    $user = User::factory()->create();
    $user->assignRole($role);

    DB::table('role_scopes')->insert([
        'user_id' => $user->id, 'role_id' => $role->id,
        'scope_kind' => 'none', 'scope_reference_id' => null,
        'granted_by' => $user->id, 'granted_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return $user->fresh();
}

/**
 * Applies one named quick filter's own query closure directly to a fresh
 * Object_ query and returns the matching ids — the reliable way to prove a
 * filter's actual matching logic in this codebase, after repeated friction
 * elsewhere in this project with Filament's Livewire table-testing helpers
 * for scope-sensitive queries. `apply()` with no $data defaults every
 * filter to active (`$data['isActive'] ?? true`), which is exactly what a
 * one-click toggle filter being "on" means.
 *
 * @return list<int>
 */
function stalenessFilterMatches(string $name): array
{
    $method = new ReflectionMethod(ObjectsTable::class, 'quickFilters');
    $method->setAccessible(true);

    /** @var list<Filter> $filters */
    $filters = $method->invoke(null);
    $filter = collect($filters)->first(fn ($filter): bool => $filter->getName() === $name);

    expect($filter)->not->toBeNull();

    return $filter->apply(Object_::query()->withUnmoderated())->pluck('id')->all();
}

it('reads the confirmation cadence from settings, not a hardcoded constant', function (): void {
    $geo = stalenessGeography();
    // Confirmed 20 days ago: stale under any cadence this test tries.
    $alwaysStale = stalenessSeedObject($geo, ['availability_last_confirmed_at' => now()->subDays(20)]);
    // Confirmed 10 days ago: stale only once the cadence tightens to 7 days
    // — this is the object whose classification must flip.
    $borderline = stalenessSeedObject($geo, ['availability_last_confirmed_at' => now()->subDays(10)]);
    // Confirmed 2 days ago: never stale under either cadence tested.
    $alwaysFresh = stalenessSeedObject($geo, ['availability_last_confirmed_at' => now()->subDays(2)]);

    // Default cadence (14 days): only the 20-day-old object is stale.
    $matches = stalenessFilterMatches('quick_stale');

    expect($matches)->toContain($alwaysStale->id)
        ->and($matches)->not->toContain($borderline->id)
        ->and($matches)->not->toContain($alwaysFresh->id);

    // Tightening the setting to 7 days makes the 10-day-old object stale
    // too — proving the threshold is read at call time, not compiled in.
    app(SettingsRepository::class)->set('availability.confirmation_period_days', 7);

    $matches = stalenessFilterMatches('quick_stale');

    expect($matches)->toContain($alwaysStale->id)
        ->and($matches)->toContain($borderline->id)
        ->and($matches)->not->toContain($alwaysFresh->id);
});

it('offers every quick filter the requirement names', function (): void {
    $method = new ReflectionMethod(ObjectsTable::class, 'quickFilters');
    $method->setAccessible(true);

    /** @var list<Filter> $filters */
    $filters = $method->invoke(null);
    $names = array_map(fn ($filter): string => $filter->getName(), $filters);

    expect($names)->toEqual([
        'quick_available', 'quick_unspecified', 'quick_stale', 'quick_active', 'quick_expired_package',
    ]);
});

it('filters to vacancies available, unspecified, active, and expired-package objects', function (): void {
    $geo = stalenessGeography();
    $available = stalenessSeedObject($geo, ['availability_status' => 'available']);
    $unspecified = stalenessSeedObject($geo, ['availability_status' => 'unspecified']);
    $active = stalenessSeedObject($geo, ['status' => 'published']);
    $draft = stalenessSeedObject($geo, ['status' => 'draft']);
    $expiredPackageObject = stalenessSeedObject($geo);

    DB::table('placement_tiers')->insert([
        'id' => 1, 'rank' => 1, 'border_colour' => '#000000', 'badge_colour' => '#000000',
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('placement_packages')->insert([
        'id' => 1, 'placement_tier_id' => 1, 'validity_days' => 30, 'price' => 10,
        'currency' => 'EUR', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('object_placements')->insert([
        'object_id' => $expiredPackageObject->id, 'placement_package_id' => 1,
        'starts_at' => now()->subDays(40), 'ends_at' => now()->subDays(5),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(stalenessFilterMatches('quick_available'))->toContain($available->id);
    expect(stalenessFilterMatches('quick_unspecified'))->toContain($unspecified->id);

    $activeMatches = stalenessFilterMatches('quick_active');
    expect($activeMatches)->toContain($active->id)
        ->and($activeMatches)->not->toContain($draft->id);

    $expiredMatches = stalenessFilterMatches('quick_expired_package');
    expect($expiredMatches)->toContain($expiredPackageObject->id)
        ->and($expiredMatches)->not->toContain($available->id);
});

it('bulk-resets a stale selection to available, journalling one history row per affected object', function (): void {
    $geo = stalenessGeography();
    $stale1 = stalenessSeedObject($geo, ['availability_status' => 'unavailable', 'availability_last_confirmed_at' => now()->subDays(30)]);
    $stale2 = stalenessSeedObject($geo, ['availability_status' => 'unavailable', 'availability_last_confirmed_at' => now()->subDays(30)]);
    $actor = stalenessActor();

    // Direct service call, not the Livewire bulk-action round trip — the
    // established, reliable pattern in this codebase for exercising
    // ObjectBulkActionService::execute() once the operation itself is
    // proven wired correctly (see ObjectBulkActionsTest for the confirmed
    // Livewire-level "every registered bulk action" coverage).
    app(ObjectBulkActionService::class)->execute(
        'reset_stale_availability',
        [$stale1->id, $stale2->id],
        [],
        $actor,
    );

    foreach ([$stale1, $stale2] as $object) {
        expect(DB::table('objects')->where('id', $object->id)->value('availability_status'))->toBe('available');

        $history = DB::table('availability_histories')->where('object_id', $object->id)->get();
        expect($history)->toHaveCount(1)
            ->and($history->first()->source)->toBe('administrator');
    }
});

it('leaves auto-reset off in a freshly seeded database, so the sweep is a no-op', function (): void {
    $geo = stalenessGeography();
    $stale = stalenessSeedObject($geo, [
        'availability_status' => 'unavailable',
        'availability_last_confirmed_at' => now()->subDays(60),
    ]);

    expect(app(SettingsRepository::class)->get('availability.auto_reset_enabled'))->toBeFalse();

    app(SweepStaleAvailabilityJob::class)->handle(
        app(AvailabilityAdministrationService::class),
        app(SettingsRepository::class),
    );

    expect(DB::table('objects')->where('id', $stale->id)->value('availability_status'))->toBe('unavailable')
        ->and(DB::table('availability_histories')->where('object_id', $stale->id)->count())->toBe(0);
});

it('auto-resets a stale selection with source automatic once enabled, leaving fresh objects untouched', function (): void {
    $geo = stalenessGeography();
    $stale = stalenessSeedObject($geo, [
        'availability_status' => 'unavailable',
        'availability_last_confirmed_at' => now()->subDays(60),
    ]);
    $fresh = stalenessSeedObject($geo, [
        'availability_status' => 'unavailable',
        'availability_last_confirmed_at' => now()->subDays(1),
    ]);

    app(SettingsRepository::class)->set('availability.auto_reset_enabled', true);

    app(SweepStaleAvailabilityJob::class)->handle(
        app(AvailabilityAdministrationService::class),
        app(SettingsRepository::class),
    );

    expect(DB::table('objects')->where('id', $stale->id)->value('availability_status'))->toBe('available')
        ->and(DB::table('objects')->where('id', $fresh->id)->value('availability_status'))->toBe('unavailable');

    $history = DB::table('availability_histories')->where('object_id', $stale->id)->first();

    expect($history->source)->toBe('automatic')
        ->and($history->changed_by)->toBeNull()
        ->and(DB::table('availability_histories')->where('object_id', $fresh->id)->count())->toBe(0);
});

it('registers the sweep on the scheduler', function (): void {
    Artisan::call('schedule:list');

    expect(Artisan::output())->toContain('availability:sweep-stale');
});
