<?php

declare(strict_types=1);

use App\Filament\Admin\Resources\Banners\BannerResource;
use App\Filament\Admin\Resources\Banners\Pages\CreateBanner;
use App\Filament\Admin\Resources\Banners\Pages\EditBanner;
use App\Models\Banner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Banner Resource
|--------------------------------------------------------------------------
|
| Full CRUD through the real Filament resource — a creative, its targeting,
| and both creative uploads created and edited in one workflow, plus the
| soft-delete/trashed-filter path a moderator would actually use.
|
*/

function bannerResourceActor(array $permissions = ['admin_panel_access', 'advertising.view', 'advertising.create', 'advertising.edit', 'advertising.delete']): User
{
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $role = Role::findOrCreate('banner_admin', 'web');
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

function bannerResourceGeography(): array
{
    $languageId = DB::table('languages')->insertGetId([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
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
    $objectTypeId = DB::table('object_types')->insertGetId([
        'key' => 'banner_resource_probe_type', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $slotId = DB::table('banner_slots')->insertGetId([
        'key' => 'banner_resource_probe_slot', 'surfaces' => json_encode(['home']),
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return compact('languageId', 'countryId', 'territoryId', 'objectTypeId', 'slotId');
}

it('creates a banner with both creatives, full targeting, and a translation, through the resource', function (): void {
    // Two disks are in play, not one: Livewire stages an upload on the
    // application's default disk before Spatie Media Library ever sees it,
    // then Media Library persists the permanent copy on its own configured
    // disk ("public" here). Faking only the second would still reach the
    // real default disk during staging.
    Storage::fake('public');
    Storage::fake(config('filesystems.default'));

    $geo = bannerResourceGeography();
    $actor = bannerResourceActor();

    Livewire::actingAs($actor)
        ->test(CreateBanner::class)
        ->fillForm([
            'banner_slot_id' => $geo['slotId'],
            'name' => 'Summer Campaign',
            'advertiser' => 'Acme Resorts',
            'destination_link' => 'https://acme.example.test',
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addMonth()->toDateString(),
            'is_active' => true,
            'display_order' => 5,
            'desktop_creative' => [UploadedFile::fake()->image('desktop.jpg', 1920, 600)],
            'mobile_creative' => [UploadedFile::fake()->image('mobile.jpg', 600, 800)],
            'territories' => [$geo['territoryId']],
            'categories' => [$geo['objectTypeId']],
            'targetLanguages' => [$geo['languageId']],
            'translations' => ['en' => ['link_text' => 'Book now']],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $banner = Banner::query()->where('name', 'Summer Campaign')->firstOrFail();

    expect($banner->banner_slot_id)->toBe($geo['slotId'])
        ->and($banner->display_order)->toBe(5)
        ->and($banner->territories()->pluck('territories.id')->all())->toBe([$geo['territoryId']])
        ->and($banner->categories()->pluck('object_types.id')->all())->toBe([$geo['objectTypeId']])
        ->and($banner->targetLanguages()->pluck('languages.id')->all())->toBe([$geo['languageId']])
        ->and($banner->getMedia('desktop_creative'))->toHaveCount(1)
        ->and($banner->getMedia('mobile_creative'))->toHaveCount(1)
        ->and(DB::table('banner_translations')->where('banner_id', $banner->id)->value('link_text'))->toBe('Book now');
});

it('edits a banner\'s window and deactivates it, preserving its targeting', function (): void {
    $geo = bannerResourceGeography();

    $bannerId = DB::table('banners')->insertGetId([
        'banner_slot_id' => $geo['slotId'], 'name' => 'Existing Campaign', 'advertiser' => 'Acme',
        'destination_link' => 'https://example.test',
        'starts_at' => now()->toDateString(), 'ends_at' => now()->addMonth()->toDateString(),
        'is_active' => true, 'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('banner_translations')->insert([
        'banner_id' => $bannerId, 'locale' => 'en', 'link_text' => 'Original text',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('banner_targets')->insert([
        'banner_id' => $bannerId, 'target_type' => 'App\\Models\\Territory', 'target_id' => $geo['territoryId'],
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $actor = bannerResourceActor();

    Livewire::actingAs($actor)
        ->test(EditBanner::class, ['record' => $bannerId])
        ->fillForm([
            'is_active' => false,
            'ends_at' => now()->addDays(3)->toDateString(),
            'translations' => ['en' => ['link_text' => 'Updated text']],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $banner = Banner::query()->findOrFail($bannerId);

    expect($banner->is_active)->toBeFalse()
        ->and($banner->ends_at->toDateString())->toBe(now()->addDays(3)->toDateString())
        ->and($banner->territories()->pluck('territories.id')->all())->toBe([$geo['territoryId']])
        ->and(DB::table('banner_translations')->where('banner_id', $bannerId)->value('link_text'))->toBe('Updated text');
});

it('soft-deletes a banner and keeps its edit page reachable for the trashed filter to link to', function (): void {
    $geo = bannerResourceGeography();

    $bannerId = DB::table('banners')->insertGetId([
        'banner_slot_id' => $geo['slotId'], 'name' => 'Retired Campaign', 'advertiser' => 'Acme',
        'destination_link' => 'https://example.test',
        'starts_at' => now()->subMonth()->toDateString(), 'ends_at' => now()->subDay()->toDateString(),
        'is_active' => true, 'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $actor = bannerResourceActor();

    Banner::query()->findOrFail($bannerId)->delete();

    expect(Banner::query()->find($bannerId))->toBeNull()
        ->and(Banner::withTrashed()->findOrFail($bannerId)->trashed())->toBeTrue();

    test()->actingAs($actor)
        ->get(BannerResource::getUrl('edit', ['record' => $bannerId], panel: 'admin'))
        ->assertSuccessful();
});

it('admits an unrestricted grant and refuses a country-scoped one, since banners are portal-wide', function (): void {
    $geo = bannerResourceGeography();
    DB::table('banners')->insert([
        'banner_slot_id' => $geo['slotId'], 'name' => 'Scope Probe', 'advertiser' => 'Acme',
        'destination_link' => 'https://example.test',
        'starts_at' => now()->toDateString(), 'ends_at' => now()->addMonth()->toDateString(),
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $unrestricted = bannerResourceActor();
    test()->actingAs($unrestricted)
        ->get(BannerResource::getUrl('index', panel: 'admin'))
        ->assertSuccessful();

    Permission::findOrCreate('admin_panel_access', 'web');
    Permission::findOrCreate('advertising.view', 'web');
    $scopedRole = Role::findOrCreate('country_scoped_advertising_banner', 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $scopedRole->syncPermissions(['admin_panel_access', 'advertising.view']);
    $scoped = User::factory()->create();
    $scoped->assignRole($scopedRole);
    DB::table('role_scopes')->insert([
        'user_id' => $scoped->id, 'role_id' => $scopedRole->id,
        'scope_kind' => 'country', 'scope_reference_id' => $geo['countryId'],
        'granted_by' => $scoped->id, 'granted_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(BannerResource::getEloquentQuery()->count())->toBe(1);
    test()->actingAs($scoped->fresh());
    expect(BannerResource::getEloquentQuery()->count())->toBe(0);
});
