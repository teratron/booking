<?php

declare(strict_types=1);

use App\Filament\Admin\Resources\BannerSlots\BannerSlotResource;
use App\Filament\Admin\Resources\BannerSlots\Pages\CreateBannerSlot;
use App\Filament\Admin\Resources\BannerSlots\Pages\EditBannerSlot;
use App\Models\BannerSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Banner Slot Registry
|--------------------------------------------------------------------------
|
| Slots are a registry, not an enum: adding a new inventory position is a
| data operation an administrator performs through this resource, never a
| deployment. Proven here by creating, editing, and deactivating a slot
| whose key this test invents on the spot.
|
*/

function bannerSlotActor(array $permissions = ['admin_panel_access', 'advertising.view', 'advertising.create', 'advertising.edit', 'advertising.delete']): User
{
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $role = Role::findOrCreate('advertising_admin', 'web');
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

function bannerSlotSeedLanguage(): void
{
    if (DB::table('languages')->where('code', 'en')->exists()) {
        return;
    }

    DB::table('languages')->insert([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

it('creates a brand-new inventory position invented on the spot, with no code change', function (): void {
    bannerSlotSeedLanguage();

    $actor = bannerSlotActor();

    // "sidebar_promo" is a slot key this test invents — never anticipated by
    // any migration or seeder — proving the surfaces list and slot registry
    // are genuinely administrator-defined data.
    Livewire::actingAs($actor)
        ->test(CreateBannerSlot::class)
        ->fillForm([
            'key' => 'sidebar_promo',
            'surfaces' => ['object', 'category'],
            'is_active' => true,
            'translations' => ['en' => ['name' => 'Sidebar Promo']],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $slot = BannerSlot::query()->where('key', 'sidebar_promo')->firstOrFail();

    expect($slot->surfaces)->toBe(['object', 'category'])
        ->and($slot->is_active)->toBeTrue()
        ->and(DB::table('banner_slot_translations')->where('banner_slot_id', $slot->id)->value('name'))->toBe('Sidebar Promo');
});

it('edits a slot\'s surfaces and deactivates it without touching its key', function (): void {
    bannerSlotSeedLanguage();

    $slotId = DB::table('banner_slots')->insertGetId([
        'key' => 'home_hero', 'surfaces' => json_encode(['home']),
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('banner_slot_translations')->insert([
        'banner_slot_id' => $slotId, 'locale' => 'en', 'name' => 'Home Hero',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $actor = bannerSlotActor();

    Livewire::actingAs($actor)
        ->test(EditBannerSlot::class, ['record' => $slotId])
        ->fillForm([
            'surfaces' => ['home', 'country', 'region'],
            'is_active' => false,
            'translations' => ['en' => ['name' => 'Home Hero (retired)']],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $slot = BannerSlot::query()->findOrFail($slotId);

    expect($slot->key)->toBe('home_hero')
        ->and($slot->surfaces)->toBe(['home', 'country', 'region'])
        ->and($slot->is_active)->toBeFalse()
        ->and(DB::table('banner_slot_translations')->where('banner_slot_id', $slotId)->value('name'))->toBe('Home Hero (retired)');
});

it('admits an unrestricted grant and refuses a country-scoped one, since slots are portal-wide inventory', function (): void {
    DB::table('banner_slots')->insert([
        'key' => 'footer_strip', 'surfaces' => json_encode(['home']),
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $unrestricted = bannerSlotActor();
    test()->actingAs($unrestricted)
        ->get(BannerSlotResource::getUrl('index', panel: 'admin'))
        ->assertSuccessful();

    Permission::findOrCreate('admin_panel_access', 'web');
    Permission::findOrCreate('advertising.view', 'web');
    $scopedRole = Role::findOrCreate('country_scoped_advertising', 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $scopedRole->syncPermissions(['admin_panel_access', 'advertising.view']);
    $scoped = User::factory()->create();
    $scoped->assignRole($scopedRole);
    DB::table('role_scopes')->insert([
        'user_id' => $scoped->id, 'role_id' => $scopedRole->id,
        'scope_kind' => 'country', 'scope_reference_id' => 1,
        'granted_by' => $scoped->id, 'granted_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(BannerSlotResource::getEloquentQuery()->count())->toBe(1);
    test()->actingAs($scoped->fresh());
    expect(BannerSlotResource::getEloquentQuery()->count())->toBe(0);
});
