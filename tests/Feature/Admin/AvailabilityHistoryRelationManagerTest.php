<?php

declare(strict_types=1);

use App\Filament\Admin\Resources\Objects\ObjectResource;
use App\Filament\Admin\Resources\Objects\Pages\EditObject;
use App\Filament\Admin\Resources\Objects\RelationManagers\AvailabilityHistoryRelationManager;
use App\Models\AvailabilityHistory;
use App\Models\Object_;
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
| Availability History Relation Manager
|--------------------------------------------------------------------------
|
| This relation manager was previously never rendered by any test — the
| table's own query scoping (does it actually list only this object's own
| rows, ordered newest first, with the changer eager-loaded?) and the
| record-resolution boundary that keeps it out of an out-of-scope
| administrator's reach were both unverified. Nothing here writes a
| history row: the append-only write path belongs to
| AvailabilityAdministrationService, exercised elsewhere.
|
*/

/** @return array{object: Object_, objectId: int, owner: User, typeDining: int} */
function availabilityHistoryFixture(): array
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
        'object_id' => $objectId, 'locale' => 'en', 'name' => 'Villa With History',
        'slug' => 'villa-with-history', 'created_at' => now(), 'updated_at' => now(),
    ]);

    return [
        'object' => Object_::query()->withUnmoderated()->findOrFail($objectId),
        'objectId' => $objectId,
        'owner' => $owner,
        'typeDining' => $typeDining,
    ];
}

/** @return int  a second, unrelated object's id — for proving the table does not leak across objects */
function availabilityHistoryOtherObject(): int
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

/** @param  list<string>  $permissions */
function availabilityHistoryActor(array $permissions, string $scopeKind, ?int $reference, string $roleKey): User
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

it('lists a seeded object\'s availability-status history rows, newest first, eager-loading who changed each one, and never leaking another object\'s rows', function (): void {
    $fixture = availabilityHistoryFixture();
    $actor = availabilityHistoryActor(['admin_panel_access', 'object.view', 'object.edit'], 'none', null, 'unrestricted_history');

    $oldestId = DB::table('availability_histories')->insertGetId([
        'object_id' => $fixture['objectId'], 'from_status' => null, 'to_status' => 'available',
        'changed_at' => now()->subDays(10), 'changed_by' => null, 'source' => 'automatic',
    ]);
    $middleId = DB::table('availability_histories')->insertGetId([
        'object_id' => $fixture['objectId'], 'from_status' => 'available', 'to_status' => 'unavailable',
        'changed_at' => now()->subDays(5), 'changed_by' => $fixture['owner']->id, 'source' => 'owner',
    ]);
    $newestId = DB::table('availability_histories')->insertGetId([
        'object_id' => $fixture['objectId'], 'from_status' => 'unavailable', 'to_status' => 'available',
        'changed_at' => now()->subDay(), 'changed_by' => $actor->id, 'source' => 'administrator',
    ]);

    // A row belonging to an entirely different object must never surface
    // through this relation manager — proving `modifyQueryUsing()` actually
    // scopes to the owner record's own relationship rather than listing the
    // whole append-only table.
    $otherObjectId = availabilityHistoryOtherObject();
    DB::table('availability_histories')->insert([
        'object_id' => $otherObjectId, 'from_status' => null, 'to_status' => 'unavailable',
        'changed_at' => now(), 'changed_by' => null, 'source' => 'automatic',
    ]);
    $otherRow = AvailabilityHistory::query()->where('object_id', $otherObjectId)->firstOrFail();

    $oldest = AvailabilityHistory::query()->findOrFail($oldestId);
    $middle = AvailabilityHistory::query()->findOrFail($middleId);
    $newest = AvailabilityHistory::query()->findOrFail($newestId);

    $component = Livewire::actingAs($actor)->test(AvailabilityHistoryRelationManager::class, [
        'ownerRecord' => $fixture['object'],
        'pageClass' => EditObject::class,
    ]);

    $component->assertCanSeeTableRecords([$newest, $middle, $oldest], inOrder: true)
        ->assertCanNotSeeTableRecords([$otherRow]);

    expect($component->instance()->getTableRecords()->count())->toBe(3);

    // Raw column state — the value the column resolves from the record,
    // ahead of formatStateUsing()'s badge-label translation.
    $component->assertTableColumnStateSet('from_status', null, $oldest)
        ->assertTableColumnStateSet('to_status', 'available', $oldest)
        ->assertTableColumnStateSet('source', 'automatic', $oldest)
        ->assertTableColumnStateSet('from_status', 'available', $middle)
        ->assertTableColumnStateSet('to_status', 'unavailable', $middle)
        ->assertTableColumnStateSet('changedBy.name', $fixture['owner']->name, $middle)
        ->assertTableColumnStateSet('source', 'owner', $middle)
        ->assertTableColumnStateSet('from_status', 'unavailable', $newest)
        ->assertTableColumnStateSet('changedBy.name', $actor->name, $newest)
        ->assertTableColumnStateSet('source', 'administrator', $newest);

    // The translated badge labels formatStateUsing() actually produces are
    // present in the rendered HTML — not just the raw state values above.
    $component->assertSee(__('panel.objects.availability.available'))
        ->assertSee(__('panel.objects.availability.unavailable'))
        ->assertSee(__('panel.objects.availability.sources.owner'))
        ->assertSee(__('panel.objects.availability.sources.administrator'))
        ->assertSee(__('panel.objects.availability.sources.automatic'))
        ->assertSee($fixture['owner']->name)
        ->assertSee($actor->name);
});

it('offers no create, edit, or delete affordance over this append-only history', function (): void {
    $fixture = availabilityHistoryFixture();
    $actor = availabilityHistoryActor(['admin_panel_access', 'object.view', 'object.edit'], 'none', null, 'unrestricted_history_actions');

    DB::table('availability_histories')->insert([
        'object_id' => $fixture['objectId'], 'from_status' => null, 'to_status' => 'available',
        'changed_at' => now(), 'changed_by' => null, 'source' => 'automatic',
    ]);

    $component = Livewire::actingAs($actor)->test(AvailabilityHistoryRelationManager::class, [
        'ownerRecord' => $fixture['object'],
        'pageClass' => EditObject::class,
    ]);

    expect($component->instance()->getTable()->getHeaderActions())->toBeEmpty()
        ->and($component->instance()->getTable()->getRecordActions())->toBeEmpty()
        ->and($component->instance()->getTable()->getToolbarActions())->toBeEmpty();
});

it('refuses a category-scoped administrator outside the object\'s category — the same not-found the edit page already enforces, before this relation manager ever renders', function (): void {
    $fixture = availabilityHistoryFixture();
    $actor = availabilityHistoryActor(
        ['admin_panel_access', 'object.view', 'object.edit'],
        'category',
        $fixture['typeDining'],
        'dining_only_history',
    );

    DB::table('availability_histories')->insert([
        'object_id' => $fixture['objectId'], 'from_status' => null, 'to_status' => 'available',
        'changed_at' => now(), 'changed_by' => null, 'source' => 'automatic',
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
