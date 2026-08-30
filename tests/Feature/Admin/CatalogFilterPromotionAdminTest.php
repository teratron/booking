<?php

declare(strict_types=1);

use App\Filament\Admin\Resources\CatalogFilterPromotions\CatalogFilterPromotionResource;
use App\Filament\Admin\Resources\CatalogFilterPromotions\Pages\CreateCatalogFilterPromotion;
use App\Filament\Admin\Resources\CatalogFilterPromotions\Pages\EditCatalogFilterPromotion;
use App\Filament\Admin\Resources\CatalogFilterPromotions\Pages\ListCatalogFilterPromotions;
use App\Models\CatalogFilterPromotion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Catalog Filter Promotion Administration
|--------------------------------------------------------------------------
|
| The single-filter indexation allowlist is read through IndexationPolicy's
| own rememberForever cache, so every write this screen makes must forget
| that cache — otherwise a newly promoted (or retired) filter value stays
| invisible to the public catalog's indexability decision until something
| else happens to evict it, which nothing here otherwise would.
|
*/

const CATALOG_FILTER_PROMOTION_CACHE_KEY = 'catalog_filter_promotions:active';

function catalogFilterPromotionAdminActor(): User
{
    $permissions = ['admin_panel_access', 'seo.view', 'seo.edit'];

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $role = Role::findOrCreate('catalog_filter_promotion_admin', 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $role->syncPermissions($permissions);

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

function catalogFilterPromotionViewerActor(): User
{
    $permissions = ['admin_panel_access', 'seo.view'];

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $role = Role::findOrCreate('catalog_filter_promotion_viewer', 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $role->syncPermissions($permissions);

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

it('lists promotions and filters the table by active state', function (): void {
    $actor = catalogFilterPromotionAdminActor();

    $active = CatalogFilterPromotion::create(['signature' => 'amenity=pool', 'is_active' => true]);
    $inactive = CatalogFilterPromotion::create(['signature' => 'amenity=sauna', 'is_active' => false]);

    Livewire::actingAs($actor)
        ->test(ListCatalogFilterPromotions::class)
        ->assertCanSeeTableRecords([$active, $inactive])
        ->filterTable('is_active', true)
        ->assertCanSeeTableRecords([$active])
        ->assertCanNotSeeTableRecords([$inactive]);
});

it('creates a promotion and drops the indexation policy cache', function (): void {
    $actor = catalogFilterPromotionAdminActor();

    Cache::put(CATALOG_FILTER_PROMOTION_CACHE_KEY, ['amenity=stale'], now()->addDay());

    Livewire::actingAs($actor)
        ->test(CreateCatalogFilterPromotion::class)
        ->fillForm(['signature' => 'amenity=pool', 'is_active' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(CatalogFilterPromotion::query()->where('signature', 'amenity=pool')->exists())->toBeTrue()
        ->and(Cache::has(CATALOG_FILTER_PROMOTION_CACHE_KEY))->toBeFalse();
});

it('refuses a second promotion with the same signature', function (): void {
    $actor = catalogFilterPromotionAdminActor();

    CatalogFilterPromotion::create(['signature' => 'amenity=pool', 'is_active' => true]);

    Livewire::actingAs($actor)
        ->test(CreateCatalogFilterPromotion::class)
        ->fillForm(['signature' => 'amenity=pool', 'is_active' => true])
        ->call('create')
        ->assertHasFormErrors(['signature']);

    expect(CatalogFilterPromotion::query()->where('signature', 'amenity=pool')->count())->toBe(1);
});

it('drops the indexation policy cache when a promotion is edited', function (): void {
    $actor = catalogFilterPromotionAdminActor();
    $promotion = CatalogFilterPromotion::create(['signature' => 'amenity=pool', 'is_active' => true]);

    Cache::put(CATALOG_FILTER_PROMOTION_CACHE_KEY, ['amenity=pool'], now()->addDay());

    Livewire::actingAs($actor)
        ->test(EditCatalogFilterPromotion::class, ['record' => $promotion->id])
        ->fillForm(['signature' => 'amenity=pool', 'is_active' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($promotion->fresh()->is_active)->toBeFalse()
        ->and(Cache::has(CATALOG_FILTER_PROMOTION_CACHE_KEY))->toBeFalse();
});

it('drops the indexation policy cache when a promotion is deleted', function (): void {
    $actor = catalogFilterPromotionAdminActor();
    $promotion = CatalogFilterPromotion::create(['signature' => 'amenity=pool', 'is_active' => true]);

    Cache::put(CATALOG_FILTER_PROMOTION_CACHE_KEY, ['amenity=pool'], now()->addDay());

    Livewire::actingAs($actor)
        ->test(EditCatalogFilterPromotion::class, ['record' => $promotion->id])
        ->callAction('delete');

    expect(CatalogFilterPromotion::query()->whereKey($promotion->id)->exists())->toBeFalse()
        ->and(Cache::has(CATALOG_FILTER_PROMOTION_CACHE_KEY))->toBeFalse();
});

it('refuses a viewer-only grant access to the create and edit pages', function (): void {
    $viewer = catalogFilterPromotionViewerActor();
    $promotion = CatalogFilterPromotion::create(['signature' => 'amenity=pool', 'is_active' => true]);

    $this->actingAs($viewer)->get(CatalogFilterPromotionResource::getUrl('create', panel: 'admin'))->assertForbidden();
    $this->actingAs($viewer)->get(CatalogFilterPromotionResource::getUrl('edit', ['record' => $promotion->id], panel: 'admin'))->assertForbidden();
});
