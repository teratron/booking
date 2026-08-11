<?php

declare(strict_types=1);

use App\Filament\Admin\Resources\ActionJournal\ActionJournalResource;
use App\Filament\Admin\Resources\Languages\LanguageResource;
use App\Filament\Admin\Resources\Modules\ModuleResource;
use App\Filament\Admin\Resources\Objects\ObjectResource;
use App\Filament\Admin\Resources\ObjectTypes\ObjectTypeResource;
use App\Filament\Admin\Resources\Territories\TerritoryResource;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use OwenIt\Auditing\Models\Audit;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Panel Authorization Matrix
|--------------------------------------------------------------------------
|
| Phase 1's scoped authorization only governs this panel if every resource
| is actually wired into it — not the ones a hand-picked test happened to
| cover. The structural case below reads the live resource registry rather
| than an enumerated list, so a resource added in a later phase is covered
| without touching this file. The scenario cases beneath it exercise the
| concrete refusal a scope mismatch produces at a real request, issued
| directly at the resource's own URL with navigation bypassed entirely — a
| hidden menu item proves nothing about what the request itself allows.
|
*/

function matrixGeography(): array
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
    $countryGe = DB::table('countries')->insertGetId([
        'code' => 'GE', 'currency' => 'GEL', 'phone_code' => '+995',
        'primary_language_id' => $languageId, 'is_active' => true, 'display_order' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $levelMd = DB::table('territory_levels')->insertGetId([
        'country_id' => $countryMd, 'depth_rank' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $levelGe = DB::table('territory_levels')->insertGetId([
        'country_id' => $countryGe, 'depth_rank' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $territoryMd = DB::table('territories')->insertGetId([
        'country_id' => $countryMd, 'level_id' => $levelMd, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $territoryGe = DB::table('territories')->insertGetId([
        'country_id' => $countryGe, 'level_id' => $levelGe, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $typeAccommodation = DB::table('object_types')->insertGetId([
        'key' => 'accommodation', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $typeDining = DB::table('object_types')->insertGetId([
        'key' => 'dining', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return compact('countryMd', 'countryGe', 'territoryMd', 'territoryGe', 'typeAccommodation', 'typeDining');
}

function matrixObject(int $countryId, int $territoryId, int $typeId): int
{
    $owner = User::factory()->create();

    $objectId = DB::table('objects')->insertGetId([
        'ulid' => (string) Str::ulid(), 'owner_id' => $owner->id,
        'object_type_id' => $typeId, 'territory_id' => $territoryId, 'country_id' => $countryId,
        'status' => 'published', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('object_translations')->insert([
        'object_id' => $objectId, 'locale' => 'en', 'name' => "Object {$objectId}",
        'slug' => "object-{$objectId}", 'created_at' => now(), 'updated_at' => now(),
    ]);

    return $objectId;
}

function matrixActor(array $permissions, string $scopeKind, ?int $reference, string $roleKey): User
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

/**
 * A single row in the table a global-registry resource manages — enough for
 * the "an unrestricted grant sees it, a scoped one does not" pair to have
 * something real to disagree about. Keyed by resource rather than iterated
 * some other way, since each registry's own table shape differs and there is
 * no generic row that fits all of them.
 */
function seedProbeRowFor(string $resourceClass): void
{
    match ($resourceClass) {
        ActionJournalResource::class => Audit::create([
            'user_type' => User::class, 'user_id' => User::factory()->create()->id,
            'event' => 'matrix_probe', 'auditable_type' => User::class, 'auditable_id' => 1,
            'old_values' => [], 'new_values' => [], 'url' => 'console',
            'ip_address' => '127.0.0.1', 'user_agent' => 'test',
            'created_at' => now(), 'updated_at' => now(),
        ]),
        LanguageResource::class => DB::table('languages')->insert([
            'code' => 'ru', 'short_label' => 'RU', 'is_active' => true, 'is_primary' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]),
        ModuleResource::class => DB::table('modules')->insert([
            'key' => 'matrix_probe_module', 'default_state' => 'disabled', 'scopable_levels' => '[]',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]),
        ObjectTypeResource::class => DB::table('object_types')->insert([
            'key' => 'matrix_probe_type', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]),
        default => throw new RuntimeException("No probe-row fixture registered for {$resourceClass}."),
    };
}

it('binds every registered admin resource to a permission prefix and a policy bound for its model', function (): void {
    $resources = Filament::getPanel('admin')->getResources();

    expect($resources)->not->toBeEmpty();

    foreach ($resources as $resourceClass) {
        $prefix = (new ReflectionProperty($resourceClass, 'permissionPrefix'))->getValue();

        expect($prefix)->not->toBe('')
            ->and(Gate::getPolicyFor($resourceClass::getModel()))->not->toBeNull();
    }
});

it('refuses a Georgia-scoped administrator an object registered in Moldova', function (): void {
    $geo = matrixGeography();
    $mdObject = matrixObject($geo['countryMd'], $geo['territoryMd'], $geo['typeAccommodation']);
    $geObject = matrixObject($geo['countryGe'], $geo['territoryGe'], $geo['typeAccommodation']);

    $actor = matrixActor(['admin_panel_access', 'object.view', 'object.edit'], 'country', $geo['countryGe'], 'ge_only');

    // The scope narrowing that hides an out-of-country row from the list
    // (`ResourceQueryScoper`) is the same query the edit page's own record
    // resolution runs — a mismatch reads as not found, not forbidden, since
    // the row genuinely is not part of what this actor's grant reaches.
    test()->actingAs($actor)
        ->get(ObjectResource::getUrl('edit', ['record' => $mdObject], panel: 'admin'))
        ->assertNotFound();

    test()->actingAs($actor)
        ->get(ObjectResource::getUrl('edit', ['record' => $geObject], panel: 'admin'))
        ->assertSuccessful();
});

it('refuses a region-scoped administrator a city entirely outside their granted subtree', function (): void {
    $geo = matrixGeography();

    $actor = matrixActor(['admin_panel_access', 'geography.view', 'geography.edit'], 'territory', $geo['territoryMd'], 'md_region_only');

    // "Outside the subtree" proven at its strongest: a territory in a
    // different country entirely, not merely a sibling one level over.
    test()->actingAs($actor)
        ->get(TerritoryResource::getUrl('edit', ['record' => $geo['territoryGe']], panel: 'admin'))
        ->assertNotFound();
});

it('refuses a category-scoped administrator an object outside their granted category', function (): void {
    $geo = matrixGeography();
    $diningObject = matrixObject($geo['countryMd'], $geo['territoryMd'], $geo['typeDining']);
    $accommodationObject = matrixObject($geo['countryMd'], $geo['territoryMd'], $geo['typeAccommodation']);

    $actor = matrixActor(['admin_panel_access', 'object.view', 'object.edit'], 'category', $geo['typeAccommodation'], 'accommodation_only');

    test()->actingAs($actor)
        ->get(ObjectResource::getUrl('edit', ['record' => $diningObject], panel: 'admin'))
        ->assertNotFound();

    test()->actingAs($actor)
        ->get(ObjectResource::getUrl('edit', ['record' => $accommodationObject], panel: 'admin'))
        ->assertSuccessful();
});

it('reaches every global-registry resource only through an unrestricted grant, never a country-scoped one', function (): void {
    $geo = matrixGeography();

    $axisLessResources = collect(Filament::getPanel('admin')->getResources())
        ->filter(function (string $resourceClass): bool {
            foreach (['countryColumn', 'territoryColumn', 'categoryColumn'] as $property) {
                if ((new ReflectionProperty($resourceClass, $property))->getValue() !== null) {
                    return false;
                }
            }

            return true;
        })
        ->values();

    // The registry drives which resources are exercised — a resource
    // declaring no scope axis in a later phase is picked up here without
    // this file changing. These four are today's, asserted so the fixture
    // switch above cannot silently stop covering one of them.
    expect($axisLessResources->all())->toEqualCanonicalizing([
        ActionJournalResource::class,
        LanguageResource::class,
        ModuleResource::class,
        ObjectTypeResource::class,
    ]);

    foreach ($axisLessResources as $resourceClass) {
        $prefix = (new ReflectionProperty($resourceClass, 'permissionPrefix'))->getValue();
        $viewPermission = "{$prefix}.view";

        seedProbeRowFor($resourceClass);

        $unrestricted = matrixActor(['admin_panel_access', $viewPermission], 'none', null, 'unrestricted_'.$prefix);
        Filament::setCurrentPanel('admin');
        test()->actingAs($unrestricted);
        expect($resourceClass::getEloquentQuery()->count())->toBeGreaterThan(0);

        $countryScoped = matrixActor(['admin_panel_access', $viewPermission], 'country', $geo['countryMd'], 'country_scoped_'.$prefix);
        test()->actingAs($countryScoped);
        expect($resourceClass::getEloquentQuery()->count())->toBe(0);
    }
});
