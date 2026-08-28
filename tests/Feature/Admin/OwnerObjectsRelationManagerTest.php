<?php

declare(strict_types=1);

use App\Filament\Admin\Resources\Owners\Pages\EditOwner;
use App\Filament\Admin\Resources\Owners\RelationManagers\ObjectsRelationManager;
use App\Models\Object_;
use App\Models\User;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
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
| Owner → Objects Relation Manager
|--------------------------------------------------------------------------
|
| The owner-edit screen's "Attached objects" tab: does it list exactly the
| objects the given owner account currently holds — never another owner's —
| render every declared column against real data, and do the attach/detach
| header and record actions actually mutate the database, journal the
| change, and notify, the same way OwnerAccountService itself is proven to
| behave in isolation elsewhere? That service-level coverage never exercises
| the Livewire component wrapping it; this file does.
|
*/

/** @return array<string, int> */
function ownerObjectsRmGeography(): array
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
    $countryUa = DB::table('countries')->insertGetId([
        'code' => 'UA', 'currency' => 'UAH', 'phone_code' => '+380',
        'primary_language_id' => $languageId, 'is_active' => true, 'display_order' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $levelMd = DB::table('territory_levels')->insertGetId([
        'country_id' => $countryMd, 'depth_rank' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $territoryMd = DB::table('territories')->insertGetId([
        'country_id' => $countryMd, 'level_id' => $levelMd, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $levelUa = DB::table('territory_levels')->insertGetId([
        'country_id' => $countryUa, 'depth_rank' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $territoryUa = DB::table('territories')->insertGetId([
        'country_id' => $countryUa, 'level_id' => $levelUa, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $typeStay = DB::table('object_types')->insertGetId([
        'key' => 'accommodation', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return compact('languageId', 'countryMd', 'countryUa', 'levelMd', 'territoryMd', 'levelUa', 'territoryUa', 'typeStay');
}

/**
 * @param  array<string, int>  $geo
 * @param  array<string, mixed>  $overrides
 */
function ownerObjectsRmSeedObject(array $geo, array $overrides = []): int
{
    return DB::table('objects')->insertGetId(array_merge([
        'ulid' => (string) Str::ulid(),
        'owner_id' => null,
        'object_type_id' => $geo['typeStay'],
        'territory_id' => $geo['territoryMd'],
        'country_id' => $geo['countryMd'],
        'status' => 'draft',
        'created_at' => now(), 'updated_at' => now(),
    ], $overrides));
}

function ownerObjectsRmSeedTranslation(int $objectId, string $name): void
{
    DB::table('object_translations')->insert([
        'object_id' => $objectId, 'locale' => 'en', 'name' => $name,
        'slug' => Str::slug($name).'-'.$objectId,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** @param  array<string, mixed>  $attributes */
function ownerObjectsRmSeedOwner(array $attributes = []): User
{
    Role::findOrCreate('object_owner', 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $owner = User::factory()->create($attributes);
    $owner->assignRole('object_owner');

    return $owner->fresh();
}

/** @param  list<string>  $permissions */
function ownerObjectsRmActor(array $permissions, string $scopeKind, ?int $reference, string $roleKey): User
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

/** @return list<string> */
function ownerObjectsRmFullPermissions(): array
{
    return ['admin_panel_access', 'user.view', 'user.edit', 'object.view', 'object.edit'];
}

// -----------------------------------------------------------------------
// Structure — the columns this screen declares, and its own bare form.
// -----------------------------------------------------------------------

it('declares exactly the object columns the owner-objects screen shows', function (): void {
    $method = new ReflectionMethod(ObjectsRelationManager::class, 'columns');
    $method->setAccessible(true);

    /** @var list<TextColumn> $columns */
    $columns = $method->invoke(null);
    $names = array_map(fn (TextColumn $column): string => $column->getName(), $columns);

    expect($names)->toEqual(['name', 'objectType.key', 'country.code', 'status']);
});

it('carries the translated tab title and no create/edit form fields of its own', function (): void {
    $owner = ownerObjectsRmSeedOwner();

    expect(ObjectsRelationManager::getTitle($owner, EditOwner::class))
        ->toBe(__('panel.owners.objects.title'));

    $manager = new ObjectsRelationManager;

    expect($manager->form(Schema::make($manager))->getComponents())->toBe([]);
});

// -----------------------------------------------------------------------
// The list — exactly this owner's own objects, with real column data.
// -----------------------------------------------------------------------

it('lists exactly the given owner\'s own objects, excluding another owner\'s entirely', function (): void {
    $geo = ownerObjectsRmGeography();
    $ownerA = ownerObjectsRmSeedOwner(['country_id' => $geo['countryMd']]);
    $ownerB = ownerObjectsRmSeedOwner(['country_id' => $geo['countryMd']]);

    $objectA1Id = ownerObjectsRmSeedObject($geo, ['owner_id' => $ownerA->id, 'status' => 'published']);
    $objectA2Id = ownerObjectsRmSeedObject($geo, ['owner_id' => $ownerA->id, 'status' => 'draft']);
    $objectBId = ownerObjectsRmSeedObject($geo, ['owner_id' => $ownerB->id]);

    ownerObjectsRmSeedTranslation($objectA1Id, 'Seaside Villa');
    // $objectA2Id deliberately carries no translation row — it exercises the
    // name column's id-fallback branch below.

    $actor = ownerObjectsRmActor(ownerObjectsRmFullPermissions(), 'none', null, 'unrestricted_objects_list');

    $objectA1 = Object_::query()->withUnmoderated()->findOrFail($objectA1Id);
    $objectA2 = Object_::query()->withUnmoderated()->findOrFail($objectA2Id);
    $objectB = Object_::query()->withUnmoderated()->findOrFail($objectBId);

    $component = Livewire::actingAs($actor)->test(ObjectsRelationManager::class, [
        'ownerRecord' => $ownerA->fresh(),
        'pageClass' => EditOwner::class,
    ]);

    $component->assertCanSeeTableRecords([$objectA1, $objectA2])
        ->assertCanNotSeeTableRecords([$objectB]);

    expect($component->instance()->getTableRecords()->count())->toBe(2);

    $component->assertTableColumnStateSet('name', 'Seaside Villa', $objectA1)
        ->assertTableColumnStateSet('name', "#{$objectA2Id}", $objectA2)
        ->assertTableColumnStateSet('objectType.key', 'accommodation', $objectA1)
        ->assertTableColumnStateSet('country.code', 'MD', $objectA1)
        ->assertTableColumnStateSet('status', 'published', $objectA1)
        ->assertTableColumnStateSet('status', 'draft', $objectA2);
});

// -----------------------------------------------------------------------
// Attaching.
// -----------------------------------------------------------------------

it('attaches a previously ownerless in-scope object through the attach action, journalling it and notifying success', function (): void {
    $geo = ownerObjectsRmGeography();
    $owner = ownerObjectsRmSeedOwner(['country_id' => $geo['countryMd']]);
    $actor = ownerObjectsRmActor(ownerObjectsRmFullPermissions(), 'none', null, 'unrestricted_objects_attach');

    $objectId = ownerObjectsRmSeedObject($geo, ['owner_id' => null]);

    Livewire::actingAs($actor)
        ->test(ObjectsRelationManager::class, ['ownerRecord' => $owner, 'pageClass' => EditOwner::class])
        ->callTableAction('attach', data: ['object_id' => $objectId])
        ->assertNotified(__('panel.owners.objects.applied'));

    expect(DB::table('objects')->where('id', $objectId)->value('owner_id'))->toBe($owner->id)
        ->and(DB::table('audits')->where('event', 'owner_object_attached')->where('auditable_id', $objectId)->count())->toBe(1);
});

it('scopes the attach action\'s own object query to the acting administrator\'s grant, excluding an out-of-scope object entirely', function (): void {
    $geo = ownerObjectsRmGeography();
    $actor = ownerObjectsRmActor(
        ['admin_panel_access', 'user.view', 'user.edit', 'object.view', 'object.edit'],
        'country',
        $geo['countryMd'],
        'md_only_objects_attach',
    );

    $inScopeId = ownerObjectsRmSeedObject($geo, ['owner_id' => null]);
    $outOfScopeId = ownerObjectsRmSeedObject($geo, [
        'owner_id' => null,
        'country_id' => $geo['countryUa'],
        'territory_id' => $geo['territoryUa'],
    ]);

    // attachableObjectsQuery() both renders the Select's own options AND
    // resolves whatever object_id the action's closure receives — a request
    // forging an out-of-scope id is refused by the same query that keeps it
    // off the rendered list in the first place, matching the identical
    // scoping proof in OwnerResourceTest for the resource-level action.
    // There is no separate "offer" step to test apart from this one query,
    // since Filament's own Select validation refuses a submitted value that
    // was never among its rendered options before the closure ever runs —
    // a full callTableAction() round trip with a forged id never reaches
    // the closure's own refusal branch at all.
    $method = new ReflectionMethod(ObjectsRelationManager::class, 'attachableObjectsQuery');
    $method->setAccessible(true);

    /** @var Builder<Object_> $query */
    $query = $method->invoke(null, $actor);
    $reachableIds = $query->pluck('id')->all();

    expect($reachableIds)->toContain($inScopeId)
        ->and($reachableIds)->not->toContain($outOfScopeId);
});

it('hides the attach action from an administrator who cannot edit this owner account', function (): void {
    $geo = ownerObjectsRmGeography();
    $owner = ownerObjectsRmSeedOwner(['country_id' => $geo['countryMd']]);
    $actor = ownerObjectsRmActor(
        ['admin_panel_access', 'object.view', 'object.edit'],
        'none',
        null,
        'no_user_edit_objects_attach',
    );

    Livewire::actingAs($actor)
        ->test(ObjectsRelationManager::class, ['ownerRecord' => $owner, 'pageClass' => EditOwner::class])
        ->assertTableActionHidden('attach');
});

// -----------------------------------------------------------------------
// Detaching.
// -----------------------------------------------------------------------

it('detaches an object through the detach action, clearing its owner, journalling it, notifying success, and dropping it from this owner\'s list', function (): void {
    $geo = ownerObjectsRmGeography();
    $owner = ownerObjectsRmSeedOwner(['country_id' => $geo['countryMd']]);
    $actor = ownerObjectsRmActor(ownerObjectsRmFullPermissions(), 'none', null, 'unrestricted_objects_detach');

    $objectId = ownerObjectsRmSeedObject($geo, ['owner_id' => $owner->id]);
    $object = Object_::query()->withUnmoderated()->findOrFail($objectId);

    $component = Livewire::actingAs($actor)->test(ObjectsRelationManager::class, [
        'ownerRecord' => $owner,
        'pageClass' => EditOwner::class,
    ]);

    $component->assertCanSeeTableRecords([$object])
        ->callTableAction('detach', $object)
        ->assertNotified(__('panel.owners.objects.applied'));

    expect(DB::table('objects')->where('id', $objectId)->value('owner_id'))->toBeNull()
        ->and(DB::table('audits')->where('event', 'owner_object_detached')->where('auditable_id', $objectId)->count())->toBe(1);

    // Reloading the table re-runs the owner-scoped relationship query — the
    // just-detached, now-ownerless object must no longer qualify.
    $component->call('loadTable')->assertCanNotSeeTableRecords([$object->fresh()]);
});

it('hides the detach action from an administrator who cannot edit this owner account', function (): void {
    $geo = ownerObjectsRmGeography();
    $owner = ownerObjectsRmSeedOwner(['country_id' => $geo['countryMd']]);
    $actor = ownerObjectsRmActor(
        ['admin_panel_access', 'object.view', 'object.edit'],
        'none',
        null,
        'no_user_edit_objects_detach',
    );

    $objectId = ownerObjectsRmSeedObject($geo, ['owner_id' => $owner->id]);
    $object = Object_::query()->withUnmoderated()->findOrFail($objectId);

    Livewire::actingAs($actor)
        ->test(ObjectsRelationManager::class, ['ownerRecord' => $owner, 'pageClass' => EditOwner::class])
        ->assertTableActionHidden('detach', $object);
});
