<?php

declare(strict_types=1);

use App\Filament\Admin\Resources\PlacementPackages\Pages\CreatePlacementPackage;
use App\Filament\Admin\Resources\PlacementTiers\Pages\EditPlacementTier;
use App\Filament\Admin\Resources\PlacementTiers\PlacementTierResource;
use App\Models\PlacementPackage;
use App\Models\PlacementTier;
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
| Placement Tier & Package Registry
|--------------------------------------------------------------------------
|
| The four ranks are structural (no create/delete screen exists — proven
| here by asserting the route set, not just reading the resource class);
| everything else — labels, badges, packages, per-category scoping — is
| administrator-editable data requiring no code change. Package parity is
| asserted directly against the model's own column set: a package varies
| tier, price, validity, and bump terms only, never anything that would
| gate object content.
|
*/

function placementRegistryActor(array $permissions = ['admin_panel_access', 'commerce.view', 'commerce.create', 'commerce.edit', 'commerce.delete']): User
{
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $role = Role::findOrCreate('commerce_admin', 'web');
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

function seedLanguage(): void
{
    if (DB::table('languages')->where('code', 'en')->exists()) {
        return;
    }

    DB::table('languages')->insert([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

it('exposes no create or delete route for tiers — the four ranks are structural, not administrator-created data', function (): void {
    $pages = PlacementTierResource::getPages();

    expect($pages)->toHaveKey('index')
        ->toHaveKey('edit')
        ->not->toHaveKey('create');
});

it('edits a tier\'s label, badge, and colours without touching its structural rank', function (): void {
    seedLanguage();

    $tierId = DB::table('placement_tiers')->insertGetId([
        'rank' => 1, 'border_colour' => '#000000', 'badge_colour' => '#111111',
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('placement_tier_translations')->insert([
        'placement_tier_id' => $tierId, 'locale' => 'en', 'label' => 'Old Label', 'badge_text' => 'Old',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $actor = placementRegistryActor();

    Livewire::actingAs($actor)
        ->test(EditPlacementTier::class, ['record' => $tierId])
        ->fillForm([
            'border_colour' => '#FFFFFF',
            'badge_colour' => '#EEEEEE',
            'badge_icon' => 'star',
            'is_active' => true,
            'translations' => ['en' => ['label' => 'New Label', 'badge_text' => 'New']],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $tier = PlacementTier::query()->findOrFail($tierId);

    expect($tier->rank)->toBe(1)
        ->and($tier->border_colour)->toBe('#FFFFFF')
        ->and(DB::table('placement_tier_translations')->where('placement_tier_id', $tierId)->value('label'))->toBe('New Label');
});

it('creates a category-scoped package binding a tier to a price and validity window, with bump terms', function (): void {
    seedLanguage();

    $tierId = DB::table('placement_tiers')->insertGetId([
        'rank' => 2, 'border_colour' => '#000000', 'badge_colour' => '#111111',
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $typeId = DB::table('object_types')->insertGetId([
        'key' => 'hotel', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $actor = placementRegistryActor();

    Livewire::actingAs($actor)
        ->test(CreatePlacementPackage::class)
        ->fillForm([
            'placement_tier_id' => $tierId,
            'object_type_id' => $typeId,
            'price' => '29.00',
            'currency' => 'eur',
            'validity_days' => 30,
            'bump_allowed' => true,
            'bump_interval_hours' => 168,
            'free_bumps_per_period' => 1,
            'paid_bump_price' => '5.00',
            'is_active' => true,
            'display_order' => 1,
            'translations' => ['en' => ['name' => 'Hotel Business']],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $package = PlacementPackage::query()->where('object_type_id', $typeId)->firstOrFail();

    expect($package->placement_tier_id)->toBe($tierId)
        ->and((float) $package->price)->toBe(29.00)
        ->and($package->currency)->toBe('EUR') // dehydrated uppercase
        ->and($package->bump_allowed)->toBeTrue()
        ->and($package->bump_interval_hours)->toBe(168)
        ->and(DB::table('placement_package_translations')->where('placement_package_id', $package->id)->value('name'))->toBe('Hotel Business');
});

it('renders the create and edit package pages — the tier selector reads a translated label on every render', function (): void {
    // A direct GET, not Livewire::test()->fillForm(): a plain fillForm call
    // can resolve the form's options closures without the same lazy-load
    // guard a full page render triggers, so this is the shape that
    // actually reproduces the crash a live sweep found.
    seedLanguage();

    $tierId = DB::table('placement_tiers')->insertGetId([
        'rank' => 5, 'border_colour' => '#000000', 'badge_colour' => '#111111',
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('placement_tier_translations')->insert([
        'placement_tier_id' => $tierId, 'locale' => 'en', 'label' => 'Rank Five',
        'badge_text' => 'Rank Five', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $packageId = DB::table('placement_packages')->insertGetId([
        'placement_tier_id' => $tierId, 'price' => 10, 'currency' => 'EUR',
        'validity_days' => 30, 'bump_allowed' => false, 'is_active' => true,
        'display_order' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $actor = placementRegistryActor();

    $this->actingAs($actor)->get('/portal-admin/placement-packages/create')->assertOk();
    $this->actingAs($actor)->get("/portal-admin/placement-packages/{$packageId}/edit")->assertOk();
});

it('package parity: a package varies only tier, price, validity, and bump terms — no content-gating column exists on the model', function (): void {
    // Structural assertion, not behavioural: the guarded column set is the
    // spec's package-parity invariant made checkable. A future migration
    // adding a photo-count or contact-count column to placement_packages
    // would fail this test, which is exactly the point.
    $columns = DB::getSchemaBuilder()->getColumnListing('placement_packages');

    $allowed = [
        'id', 'placement_tier_id', 'object_type_id', 'price', 'currency', 'validity_days',
        'bump_allowed', 'bump_interval_hours', 'free_bumps_per_period', 'paid_bump_price',
        'is_active', 'display_order', 'created_at', 'updated_at',
    ];

    expect($columns)->toEqualCanonicalizing($allowed);
});

it('lets an unrestricted grant reach the registry and refuses a country-scoped one, since packages are portal-wide configuration', function (): void {
    DB::table('placement_tiers')->insert([
        'rank' => 3, 'border_colour' => '#000000', 'badge_colour' => '#111111',
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $unrestricted = placementRegistryActor();
    $this->actingAs($unrestricted)
        ->get(PlacementTierResource::getUrl('index', panel: 'admin'))
        ->assertSuccessful();

    Permission::findOrCreate('commerce.view', 'web');
    Permission::findOrCreate('admin_panel_access', 'web');
    $scopedRole = Role::findOrCreate('country_scoped_commerce', 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $scopedRole->syncPermissions(['admin_panel_access', 'commerce.view']);
    $scoped = User::factory()->create();
    $scoped->assignRole($scopedRole);
    DB::table('role_scopes')->insert([
        'user_id' => $scoped->id, 'role_id' => $scopedRole->id,
        'scope_kind' => 'country', 'scope_reference_id' => 1,
        'granted_by' => $scoped->id, 'granted_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(PlacementTierResource::getEloquentQuery()->count())->toBe(1);
    $this->actingAs($scoped->fresh());
    expect(PlacementTierResource::getEloquentQuery()->count())->toBe(0);
});
