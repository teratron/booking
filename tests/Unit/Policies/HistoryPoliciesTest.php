<?php

declare(strict_types=1);

use App\Models\AvailabilityHistory;
use App\Models\PlacementHistory;
use App\Models\User;
use App\Policies\AvailabilityHistoryPolicy;
use App\Policies\PlacementHistoryPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| AvailabilityHistoryPolicy / PlacementHistoryPolicy — view()
|--------------------------------------------------------------------------
|
| Both policies' viewAny() is already exercised indirectly through
| RelationManager::canViewForRecord() in AvailabilityHistoryRelationManagerTest
| and PlacementHistoryRelationManagerTest, but neither test ever calls
| view($user, $record) — the method Filament consults when a specific
| history row (not just the tab) is checked. These tests call it directly,
| against a real persisted row of each model, for both a permitted and a
| denied actor.
|
*/

/** @return int  a minimal object row, just enough to hang a history row off of */
function historyPoliciesObject(): int
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

    return DB::table('objects')->insertGetId([
        'ulid' => (string) Str::ulid(), 'owner_id' => null,
        'object_type_id' => $typeId, 'territory_id' => $territoryId, 'country_id' => $countryId,
        'status' => 'draft', 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function historyPoliciesAvailabilityRow(int $objectId): AvailabilityHistory
{
    $id = DB::table('availability_histories')->insertGetId([
        'object_id' => $objectId, 'from_status' => null, 'to_status' => 'available',
        'changed_at' => now(), 'changed_by' => null, 'source' => 'automatic',
    ]);

    return AvailabilityHistory::query()->findOrFail($id);
}

function historyPoliciesPlacementRow(int $objectId): PlacementHistory
{
    $tierId = DB::table('placement_tiers')->insertGetId([
        'rank' => 1, 'border_colour' => '#000000', 'badge_colour' => '#111111',
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $packageId = DB::table('placement_packages')->insertGetId([
        'placement_tier_id' => $tierId, 'price' => 10, 'currency' => 'EUR', 'validity_days' => 30,
        'bump_allowed' => true, 'is_active' => true, 'display_order' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $id = DB::table('placement_histories')->insertGetId([
        'object_id' => $objectId, 'placement_package_id' => $packageId,
        'starts_at' => now()->toDateString(), 'ends_at' => null,
        'amount' => 50, 'currency' => 'EUR', 'status' => 'granted_free', 'granted_by' => null,
        'comment' => null, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return PlacementHistory::query()->findOrFail($id);
}

/** @param  list<string>  $permissions */
function historyPoliciesActor(array $permissions, string $roleKey): User
{
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $role = Role::findOrCreate($roleKey, 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $role->syncPermissions($permissions);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user->fresh();
}

it('grants AvailabilityHistoryPolicy::view() to an actor holding object.view, against a real persisted history row', function (): void {
    $objectId = historyPoliciesObject();
    $history = historyPoliciesAvailabilityRow($objectId);

    $permitted = historyPoliciesActor(['admin_panel_access', 'object.view'], 'availability_history_view_permitted');

    expect(app(AvailabilityHistoryPolicy::class)->view($permitted, $history))->toBeTrue();
});

it('refuses AvailabilityHistoryPolicy::view() to an actor without object.view, against the same persisted history row', function (): void {
    $objectId = historyPoliciesObject();
    $history = historyPoliciesAvailabilityRow($objectId);

    $denied = User::factory()->create();

    expect(app(AvailabilityHistoryPolicy::class)->view($denied, $history))->toBeFalse();
});

it('grants PlacementHistoryPolicy::view() to an actor holding object.view, against a real persisted history row', function (): void {
    $objectId = historyPoliciesObject();
    $history = historyPoliciesPlacementRow($objectId);

    $permitted = historyPoliciesActor(['admin_panel_access', 'object.view'], 'placement_history_view_permitted');

    expect(app(PlacementHistoryPolicy::class)->view($permitted, $history))->toBeTrue();
});

it('refuses PlacementHistoryPolicy::view() to an actor without object.view, against the same persisted history row', function (): void {
    $objectId = historyPoliciesObject();
    $history = historyPoliciesPlacementRow($objectId);

    $denied = User::factory()->create();

    expect(app(PlacementHistoryPolicy::class)->view($denied, $history))->toBeFalse();
});
