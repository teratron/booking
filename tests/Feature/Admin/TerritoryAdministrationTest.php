<?php

declare(strict_types=1);

use App\Exceptions\TerritoryCycleException;
use App\Filament\Admin\Resources\Territories\Pages\EditTerritory;
use App\Filament\Admin\Resources\Territories\TerritoryResource;
use App\Models\Territory;
use App\Models\User;
use App\Services\Geography\TerritoryAdministrator;
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
| Territory Administration
|--------------------------------------------------------------------------
|
| Two claims matter beyond the ordinary field save: the blast-radius count
| behind the reparent confirmation is computed over the whole subtree, not
| the immediate children, and a reparent that would create a cycle is
| refused before anything is written.
|
*/

function territoryFixture(): array
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
    $level = DB::table('territory_levels')->insertGetId([
        'country_id' => $countryMd, 'depth_rank' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);

    // region -> city -> resort, three levels deep, plus a sibling region.
    $region = DB::table('territories')->insertGetId([
        'country_id' => $countryMd, 'level_id' => $level, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $city = DB::table('territories')->insertGetId([
        'country_id' => $countryMd, 'level_id' => $level, 'parent_id' => $region,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $resort = DB::table('territories')->insertGetId([
        'country_id' => $countryMd, 'level_id' => $level, 'parent_id' => $city,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $siblingRegion = DB::table('territories')->insertGetId([
        'country_id' => $countryMd, 'level_id' => $level, 'created_at' => now(), 'updated_at' => now(),
    ]);

    foreach ([$region => 'Region', $city => 'City', $resort => 'Resort', $siblingRegion => 'Sibling Region'] as $id => $name) {
        DB::table('territory_translations')->insert([
            'territory_id' => $id, 'country_id' => $countryMd, 'locale' => 'en', 'name' => $name,
            'slug' => Str::slug($name).'-'.$id, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    $typeId = DB::table('object_types')->insertGetId([
        'key' => 'accommodation', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $owner = User::factory()->create();

    // One object on the city, one on the resort — both within the region's subtree.
    foreach ([$city, $resort] as $territoryId) {
        DB::table('objects')->insert([
            'ulid' => (string) Str::ulid(), 'owner_id' => $owner->id,
            'object_type_id' => $typeId, 'territory_id' => $territoryId, 'country_id' => $countryMd,
            'status' => 'published', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    return [
        'countryMd' => $countryMd, 'countryUa' => $countryUa,
        'region' => Territory::query()->findOrFail($region),
        'city' => $city, 'resort' => $resort, 'siblingRegion' => $siblingRegion,
    ];
}

function territoryActor(array $permissions, string $scopeKind, ?int $reference, string $roleKey): User
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

it('computes the reparent blast radius over the whole subtree, not just immediate children', function (): void {
    $fixture = territoryFixture();

    $radius = app(TerritoryAdministrator::class)->blastRadius($fixture['region']);

    // city + resort = 2 descendants; both carry an object = 2 objects — the
    // resort is two levels down and must still be counted.
    expect($radius['descendants'])->toBe(2)
        ->and($radius['objects'])->toBe(2);
});

it('rejects a reparent that would make a territory its own ancestor', function (): void {
    $fixture = territoryFixture();
    $city = Territory::query()->findOrFail($fixture['city']);
    $actor = territoryActor(['admin_panel_access', 'geography.view', 'geography.edit'], 'none', null, 'unrestricted');

    // Moving the region under its own grandchild (the resort) would make the
    // region its own descendant's descendant.
    expect(fn () => app(TerritoryAdministrator::class)->reparent($fixture['region'], $fixture['resort'], $actor))
        ->toThrow(TerritoryCycleException::class);

    // Moving a node under itself is refused too.
    expect(fn () => app(TerritoryAdministrator::class)->reparent($city, $fixture['city'], $actor))
        ->toThrow(TerritoryCycleException::class);

    expect($fixture['region']->fresh()->parent_id)->toBeNull();
});

it('rewrites the denormalized country for every descendant when reparenting across a country boundary', function (): void {
    $fixture = territoryFixture();
    $actor = territoryActor(['admin_panel_access', 'geography.view', 'geography.edit'], 'none', null, 'unrestricted');

    $uaLevel = DB::table('territory_levels')->insertGetId([
        'country_id' => $fixture['countryUa'], 'depth_rank' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $uaRoot = DB::table('territories')->insertGetId([
        'country_id' => $fixture['countryUa'], 'level_id' => $uaLevel, 'created_at' => now(), 'updated_at' => now(),
    ]);

    app(TerritoryAdministrator::class)->reparent($fixture['region'], $uaRoot, $actor);

    expect(Territory::query()->findOrFail($fixture['region']->id)->country_id)->toBe($fixture['countryUa'])
        ->and(Territory::query()->findOrFail($fixture['city'])->country_id)->toBe($fixture['countryUa'])
        ->and(Territory::query()->findOrFail($fixture['resort'])->country_id)->toBe($fixture['countryUa']);
});

it('recomputes the cached slug path for every descendant after a reparent', function (): void {
    $fixture = territoryFixture();
    $actor = territoryActor(['admin_panel_access', 'geography.view', 'geography.edit'], 'none', null, 'unrestricted');

    app(TerritoryAdministrator::class)->reparent(Territory::query()->findOrFail($fixture['city']), $fixture['siblingRegion'], $actor);

    $cityPath = DB::table('territory_translations')->where('territory_id', $fixture['city'])->value('full_slug_path');
    $resortPath = DB::table('territory_translations')->where('territory_id', $fixture['resort'])->value('full_slug_path');

    expect($cityPath)->toContain('sibling-region')->toContain('city')
        ->and($resortPath)->toContain('sibling-region')->toContain('city')->toContain('resort');
});

it('journals the reparent with the affected counts', function (): void {
    $fixture = territoryFixture();
    $actor = territoryActor(['admin_panel_access', 'geography.view', 'geography.edit'], 'none', null, 'unrestricted');

    app(TerritoryAdministrator::class)->reparent($fixture['region'], $fixture['siblingRegion'], $actor);

    $entry = DB::table('audits')->where('event', 'territory_reparented')->first();

    expect($entry)->not->toBeNull()
        ->and($entry->new_values)->toContain('"affected_descendants":2')
        ->and($entry->new_values)->toContain('"affected_objects":2');
});

it('deactivates a territory without detaching its objects', function (): void {
    $fixture = territoryFixture();
    $actor = territoryActor(['admin_panel_access', 'geography.view', 'geography.edit'], 'none', null, 'unrestricted');

    Livewire::actingAs($actor)
        ->test(EditTerritory::class, ['record' => $fixture['city']])
        ->fillForm(['is_active' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Territory::query()->findOrFail($fixture['city'])->is_active)->toBeFalse()
        ->and(DB::table('objects')->where('territory_id', $fixture['city'])->count())->toBe(1);
});

it('admits an unrestricted administrator and refuses a country-scoped one outside their country', function (): void {
    $fixture = territoryFixture();

    $unrestricted = territoryActor(['admin_panel_access', 'geography.view', 'geography.edit'], 'none', null, 'unrestricted');
    $this->actingAs($unrestricted)
        ->get(TerritoryResource::getUrl('edit', ['record' => $fixture['region']->id], panel: 'admin'))
        ->assertSuccessful();

    // Refused, not admitted — Filament's default record resolution (unlike
    // ObjectResource's, which overrides it to also reach trashed rows)
    // resolves route bindings through the same scoped query the list uses,
    // so an out-of-scope territory reads as not found rather than 403. Both
    // are "refused"; neither renders the edit page.
    $uaOnly = territoryActor(['admin_panel_access', 'geography.view', 'geography.edit'], 'country', $fixture['countryUa'], 'ua_only');
    $this->actingAs($uaOnly)
        ->get(TerritoryResource::getUrl('edit', ['record' => $fixture['region']->id], panel: 'admin'))
        ->assertNotFound();
});

it('renders the list including a country-root territory with no parent', function (): void {
    $fixture = territoryFixture();
    $actor = territoryActor(['admin_panel_access', 'geography.view', 'geography.edit'], 'none', null, 'unrestricted');

    // The region is itself a country root — no parent — the exact row shape
    // that a nullsafe-on-the-relation shortcut would crash rendering.
    $this->actingAs($actor)
        ->get(TerritoryResource::getUrl('index', panel: 'admin'))
        ->assertSuccessful()
        ->assertSee('Region')
        ->assertSee('City');
});

it('lets a region-scoped grant reach every descendant through the subtree expansion', function (): void {
    $fixture = territoryFixture();
    $regionScoped = territoryActor(['admin_panel_access', 'geography.view', 'geography.edit'], 'territory', $fixture['region']->id, 'region_scoped');

    expect($regionScoped->can('update', Territory::query()->findOrFail($fixture['resort'])))->toBeTrue()
        ->and($regionScoped->can('update', Territory::query()->findOrFail($fixture['siblingRegion'])))->toBeFalse();
});
