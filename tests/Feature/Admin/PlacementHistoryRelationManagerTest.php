<?php

declare(strict_types=1);

use App\Filament\Admin\Resources\Objects\ObjectResource;
use App\Filament\Admin\Resources\Objects\Pages\EditObject;
use App\Filament\Admin\Resources\Objects\RelationManagers\PlacementHistoryRelationManager;
use App\Models\Object_;
use App\Models\PlacementHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Placement History Relation Manager
|--------------------------------------------------------------------------
|
| This relation manager was added alongside the placement-grant admin
| surface, but PlacementGrantAdministrationTest only ever drives the grant/
| pin/unpin actions and reads the history back through the Eloquent
| relationship directly — it never renders this relation manager's own
| table. That left the query scoping (does it list only this object's own
| grants, newest first, with the package and granting administrator eager-
| loaded?) and the record-resolution boundary that keeps it out of an
| out-of-scope administrator's reach both unverified. Nothing here writes a
| history row: the append-only write path belongs to
| PlacementLifecycleService, exercised elsewhere.
|
*/

/** @return array{object: Object_, objectId: int, owner: User, typeDining: int} */
function placementHistoryFixture(): array
{
    $languageId = DB::table('languages')->insertGetId([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $countryMd = DB::table('countries')->insertGetId([
        'code' => 'MD', 'currency' => 'MDL', 'phone_code' => '+373',
        'primary_language_id' => $languageId, 'is_active' => true, 'display_order' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $levelMd = DB::table('territory_levels')->insertGetId([
        'country_id' => $countryMd, 'depth_rank' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $territoryMd = DB::table('territories')->insertGetId([
        'country_id' => $countryMd, 'level_id' => $levelMd, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $typeStay = DB::table('object_types')->insertGetId([
        'key' => 'accommodation', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $typeDining = DB::table('object_types')->insertGetId([
        'key' => 'dining', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $owner = User::factory()->create();

    $objectId = DB::table('objects')->insertGetId([
        'ulid' => (string) Str::ulid(), 'owner_id' => $owner->id,
        'object_type_id' => $typeStay, 'territory_id' => $territoryMd, 'country_id' => $countryMd,
        'status' => 'draft', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('object_translations')->insert([
        'object_id' => $objectId, 'locale' => 'en', 'name' => 'Villa With Placement History',
        'slug' => 'villa-with-placement-history', 'created_at' => now(), 'updated_at' => now(),
    ]);

    return [
        'object' => Object_::query()->withUnmoderated()->findOrFail($objectId),
        'objectId' => $objectId,
        'owner' => $owner,
        'typeDining' => $typeDining,
    ];
}

/** @return int  a second, unrelated object's id — for proving the table does not leak across objects */
function placementHistoryOtherObject(): int
{
    $languageId = DB::table('languages')->insertGetId([
        'code' => 'ro', 'short_label' => 'RO', 'is_active' => true, 'is_primary' => false,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $countryId = DB::table('countries')->insertGetId([
        'code' => 'UA', 'currency' => 'UAH', 'phone_code' => '+380',
        'primary_language_id' => $languageId, 'is_active' => true, 'display_order' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $levelId = DB::table('territory_levels')->insertGetId([
        'country_id' => $countryId, 'depth_rank' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $territoryId = DB::table('territories')->insertGetId([
        'country_id' => $countryId, 'level_id' => $levelId, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $typeId = DB::table('object_types')->insertGetId([
        'key' => 'unrelated_type', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return DB::table('objects')->insertGetId([
        'ulid' => (string) Str::ulid(), 'owner_id' => null,
        'object_type_id' => $typeId, 'territory_id' => $territoryId, 'country_id' => $countryId,
        'status' => 'draft', 'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** A placement package with a real translated name, so the relation manager's `package.name` column has something other than the null placeholder to resolve. */
function placementHistoryPackage(int $rank, string $name): int
{
    $tierId = DB::table('placement_tiers')->insertGetId([
        'rank' => $rank, 'border_colour' => '#000000', 'badge_colour' => '#111111',
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $packageId = DB::table('placement_packages')->insertGetId([
        'placement_tier_id' => $tierId, 'price' => 10, 'currency' => 'EUR', 'validity_days' => 30,
        'bump_allowed' => true, 'is_active' => true, 'display_order' => $rank,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    DB::table('placement_package_translations')->insert([
        'placement_package_id' => $packageId, 'locale' => 'en', 'name' => $name,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return $packageId;
}

/** @param  list<string>  $permissions */
function placementHistoryActor(array $permissions, string $scopeKind, ?int $reference, string $roleKey): User
{
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $role = Role::findOrCreate($roleKey, 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $role->syncPermissions($permissions);

    $user = User::factory()->create();
    $user->assignRole($role);

    DB::table('role_scopes')->insert([
        'user_id' => $user->id, 'role_id' => $role->id,
        'scope_kind' => $scopeKind, 'scope_reference_id' => $reference,
        'granted_by' => $user->id, 'granted_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return $user->fresh();
}

it('lists a seeded object\'s placement-grant history rows, newest first, eager-loading the package and granting administrator, and never leaking another object\'s rows', function (): void {
    $fixture = placementHistoryFixture();
    $actor = placementHistoryActor(['admin_panel_access', 'object.view', 'object.edit'], 'none', null, 'unrestricted_placement_history');

    $basicPackageId = placementHistoryPackage(1, 'Basic Listing');
    $standardPackageId = placementHistoryPackage(2, 'Standard Listing');
    $premiumPackageId = placementHistoryPackage(3, 'Premium Listing');

    $oldestId = DB::table('placement_histories')->insertGetId([
        'object_id' => $fixture['objectId'], 'placement_package_id' => $basicPackageId,
        'starts_at' => now()->subDays(20)->toDateString(), 'ends_at' => now()->subDays(10)->toDateString(),
        'amount' => 50, 'currency' => 'EUR', 'status' => 'paid', 'granted_by' => null,
        'comment' => null, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $middleId = DB::table('placement_histories')->insertGetId([
        'object_id' => $fixture['objectId'], 'placement_package_id' => $standardPackageId,
        'starts_at' => now()->subDays(10)->toDateString(), 'ends_at' => now()->subDay()->toDateString(),
        'amount' => 100, 'currency' => 'EUR', 'status' => 'partially_paid', 'granted_by' => $fixture['owner']->id,
        'comment' => 'Renewed subscription', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $newestId = DB::table('placement_histories')->insertGetId([
        'object_id' => $fixture['objectId'], 'placement_package_id' => $premiumPackageId,
        'starts_at' => now()->subDay()->toDateString(), 'ends_at' => null,
        'amount' => 150, 'currency' => 'EUR', 'status' => 'granted_free', 'granted_by' => $actor->id,
        'comment' => null, 'created_at' => now(), 'updated_at' => now(),
    ]);

    // A row belonging to an entirely different object must never surface
    // through this relation manager — proving `modifyQueryUsing()` actually
    // scopes to the owner record's own relationship rather than listing the
    // whole append-only table.
    $otherObjectId = placementHistoryOtherObject();
    DB::table('placement_histories')->insert([
        'object_id' => $otherObjectId, 'placement_package_id' => $basicPackageId,
        'starts_at' => now()->toDateString(), 'ends_at' => null,
        'amount' => 999, 'currency' => 'EUR', 'status' => 'paid', 'granted_by' => null,
        'comment' => null, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $otherRow = PlacementHistory::query()->where('object_id', $otherObjectId)->firstOrFail();

    $oldest = PlacementHistory::query()->findOrFail($oldestId);
    $middle = PlacementHistory::query()->findOrFail($middleId);
    $newest = PlacementHistory::query()->findOrFail($newestId);

    $component = Livewire::actingAs($actor)->test(PlacementHistoryRelationManager::class, [
        'ownerRecord' => $fixture['object'],
        'pageClass' => EditObject::class,
    ]);

    $component->assertCanSeeTableRecords([$newest, $middle, $oldest], inOrder: true)
        ->assertCanNotSeeTableRecords([$otherRow]);

    expect($component->instance()->getTableRecords()->count())->toBe(3);

    // Raw column state — the value the column resolves from the record,
    // ahead of formatStateUsing()'s translation and currency formatting.
    $component->assertTableColumnStateSet('package.name', 'Basic Listing', $oldest)
        ->assertTableColumnStateSet('status', 'paid', $oldest)
        ->assertTableColumnStateSet('package.name', 'Standard Listing', $middle)
        ->assertTableColumnStateSet('status', 'partially_paid', $middle)
        ->assertTableColumnStateSet('grantedBy.name', $fixture['owner']->name, $middle)
        ->assertTableColumnStateSet('comment', 'Renewed subscription', $middle)
        ->assertTableColumnStateSet('package.name', 'Premium Listing', $newest)
        ->assertTableColumnStateSet('status', 'granted_free', $newest)
        ->assertTableColumnStateSet('grantedBy.name', $actor->name, $newest);

    // The translated badge labels and formatted amount formatStateUsing()
    // actually produces are present in the rendered HTML — not just the
    // raw state values above.
    $component->assertSee(__('panel.objects.placement.ledger_status_options.paid'))
        ->assertSee(__('panel.objects.placement.ledger_status_options.partially_paid'))
        ->assertSee(__('panel.objects.placement.ledger_status_options.granted_free'))
        ->assertSee(__('panel.objects.placement.open'))
        ->assertSee('50.00 EUR')
        ->assertSee('100.00 EUR')
        ->assertSee('150.00 EUR')
        ->assertSee($fixture['owner']->name)
        ->assertSee($actor->name);
});

it('offers no create, edit, or delete affordance over this append-only history', function (): void {
    $fixture = placementHistoryFixture();
    $actor = placementHistoryActor(['admin_panel_access', 'object.view', 'object.edit'], 'none', null, 'unrestricted_placement_history_actions');
    $packageId = placementHistoryPackage(1, 'Basic Listing');

    DB::table('placement_histories')->insert([
        'object_id' => $fixture['objectId'], 'placement_package_id' => $packageId,
        'starts_at' => now()->toDateString(), 'ends_at' => null,
        'amount' => 50, 'currency' => 'EUR', 'status' => 'granted_free', 'granted_by' => null,
        'comment' => null, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $component = Livewire::actingAs($actor)->test(PlacementHistoryRelationManager::class, [
        'ownerRecord' => $fixture['object'],
        'pageClass' => EditObject::class,
    ]);

    expect($component->instance()->getTable()->getHeaderActions())->toBeEmpty()
        ->and($component->instance()->getTable()->getRecordActions())->toBeEmpty()
        ->and($component->instance()->getTable()->getToolbarActions())->toBeEmpty();
});

it('lets a country-scoped administrator whose grant covers this object\'s own country see the tab at all, not only an unrestricted one', function (): void {
    $fixture = placementHistoryFixture();
    $actor = placementHistoryActor(
        ['admin_panel_access', 'object.view', 'object.edit'],
        'country',
        DB::table('objects')->where('id', $fixture['objectId'])->value('country_id'),
        'md_scoped_placement_history',
    );

    // Filament's own RelationManager::canViewForRecord() — the tab-visibility
    // gate EditObject consults, resolving to Policy::viewAny($user) with no
    // record — is the actual authorization surface here, not a direct
    // Livewire mount of the relation manager (which never calls it at all).
    // PlacementHistoryPolicy::viewAny() reads a plain user.can('object.view')
    // check rather than ScopedPolicy::authorize() with no scope target — the
    // latter treats an omitted target as "does not match" for every scope
    // kind except unrestricted, which would hide this exact administrator's
    // tab even though the object edit page they are already on confirmed
    // their country-scoped grant covers this very object.
    $this->actingAs($actor);

    expect(PlacementHistoryRelationManager::canViewForRecord($fixture['object'], EditObject::class))->toBeTrue();
});

it('refuses a category-scoped administrator outside the object\'s category — the same not-found the edit page already enforces, before this relation manager ever renders', function (): void {
    $fixture = placementHistoryFixture();
    $actor = placementHistoryActor(
        ['admin_panel_access', 'object.view', 'object.edit'],
        'category',
        $fixture['typeDining'],
        'dining_only_placement_history',
    );
    $packageId = placementHistoryPackage(1, 'Basic Listing');

    DB::table('placement_histories')->insert([
        'object_id' => $fixture['objectId'], 'placement_package_id' => $packageId,
        'starts_at' => now()->toDateString(), 'ends_at' => null,
        'amount' => 50, 'currency' => 'EUR', 'status' => 'granted_free', 'granted_by' => null,
        'comment' => null, 'created_at' => now(), 'updated_at' => now(),
    ]);

    // Reaching this relation manager at all requires reaching the object's
    // edit page first; a category mismatch narrows the page's own scoped
    // record query to nothing, so the request 404s before the relation
    // manager tab is ever mounted — the exact pattern this project already
    // establishes for scoped-resource record resolution.
    $this->actingAs($actor)
        ->get(ObjectResource::getUrl('edit', ['record' => $fixture['objectId']], panel: 'admin'))
        ->assertNotFound();
});
