<?php

declare(strict_types=1);

use App\Filament\Admin\Resources\FinancialRecords\FinancialRecordResource;
use App\Filament\Admin\Resources\FinancialRecords\Pages\CreateFinancialRecord;
use App\Filament\Admin\Resources\FinancialRecords\Pages\EditFinancialRecord;
use App\Filament\Admin\Resources\PlacementPackages\Pages\EditPlacementPackage;
use App\Filament\Admin\Resources\PlacementPackages\PlacementPackageResource;
use App\Models\FinancialRecord;
use App\Models\PlacementPackage;
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
| EditPlacementPackage / CreateFinancialRecord / EditFinancialRecord
|--------------------------------------------------------------------------
|
| Both resources touch money directly, so each page's own life-cycle hooks
| are exercised end to end through Livewire rather than assumed: the
| translation fill/save round trip on the package edit page, and the
| subject_kind-to-foreign-key derivation on both financial record pages
| (fill derives it from the stored row, save nulls whichever key was not
| chosen even when the form never submits it). Every path also proves the
| commerce.edit / finance.edit permission gate refuses a read-only grant.
|
*/

/** @param  list<string>  $permissions */
function placementFinanceAdminPagesActor(array $permissions, string $roleKey): User
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
        'scope_kind' => 'none', 'scope_reference_id' => null,
        'granted_by' => $user->id, 'granted_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return $user->fresh();
}

function placementFinanceAdminPagesSeedLanguage(): void
{
    if (DB::table('languages')->where('code', 'en')->exists()) {
        return;
    }

    DB::table('languages')->insert([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** @param  array<string, mixed>  $overrides */
function placementFinanceAdminPagesPackage(array $overrides = []): PlacementPackage
{
    $nextRank = 1 + (int) DB::table('placement_tiers')->max('rank');
    $tierId = DB::table('placement_tiers')->insertGetId([
        'rank' => $nextRank, 'border_colour' => '#000000', 'badge_colour' => '#111111',
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $packageId = DB::table('placement_packages')->insertGetId(array_merge([
        'placement_tier_id' => $tierId, 'price' => 10, 'currency' => 'EUR', 'validity_days' => 30,
        'bump_allowed' => false, 'is_active' => true, 'display_order' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ], $overrides));

    DB::table('placement_package_translations')->insert([
        'placement_package_id' => $packageId, 'locale' => 'en', 'name' => 'Original Name',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return PlacementPackage::query()->findOrFail($packageId);
}

/** @return array{country: int, territory: int, type: int} */
function placementFinanceAdminPagesGeography(): array
{
    $languageId = DB::table('languages')->insertGetId([
        // languages.code is varchar(10) — a random suffix must stay short
        // enough to fit alongside a readable prefix.
        'code' => 'g'.Str::random(5), 'short_label' => 'EN', 'is_active' => true, 'is_primary' => false,
        'display_order' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $countryId = DB::table('countries')->insertGetId([
        'code' => Str::upper(Str::random(2)), 'currency' => 'MDL', 'phone_code' => '+373',
        'primary_language_id' => $languageId, 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $levelId = DB::table('territory_levels')->insertGetId([
        'country_id' => $countryId, 'depth_rank' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $territoryId = DB::table('territories')->insertGetId([
        'country_id' => $countryId, 'level_id' => $levelId, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $typeId = DB::table('object_types')->insertGetId([
        'key' => 'hotel_'.Str::random(6), 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return ['country' => $countryId, 'territory' => $territoryId, 'type' => $typeId];
}

function placementFinanceAdminPagesObject(): int
{
    $geo = placementFinanceAdminPagesGeography();
    $owner = User::factory()->create();

    return DB::table('objects')->insertGetId([
        'ulid' => (string) Str::ulid(), 'owner_id' => $owner->id,
        'object_type_id' => $geo['type'], 'territory_id' => $geo['territory'], 'country_id' => $geo['country'],
        'status' => 'published', 'moderation_status' => 'approved',
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function placementFinanceAdminPagesBanner(): int
{
    $slotId = DB::table('banner_slots')->insertGetId([
        'key' => 'test-slot-'.Str::random(6), 'surfaces' => json_encode(['home']),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return DB::table('banners')->insertGetId([
        'banner_slot_id' => $slotId, 'name' => 'Test Banner', 'advertiser' => 'Test Advertiser',
        'destination_link' => 'https://example.test', 'display_order' => 0,
        'starts_at' => now()->toDateString(), 'ends_at' => now()->addMonth()->toDateString(),
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

// --- EditPlacementPackage ------------------------------------------------

it('fills the edit form with the package\'s existing per-locale translation', function (): void {
    placementFinanceAdminPagesSeedLanguage();
    $package = placementFinanceAdminPagesPackage();
    $actor = placementFinanceAdminPagesActor(['admin_panel_access', 'commerce.view', 'commerce.edit'], 'commerce_editor');

    Livewire::actingAs($actor)
        ->test(EditPlacementPackage::class, ['record' => $package->id])
        ->assertFormSet(['translations.en.name' => 'Original Name']);
});

it('persists updated price, currency, and bump fields, and updates the existing translation row rather than duplicating it', function (): void {
    placementFinanceAdminPagesSeedLanguage();
    $package = placementFinanceAdminPagesPackage(['bump_allowed' => false]);
    $actor = placementFinanceAdminPagesActor(['admin_panel_access', 'commerce.view', 'commerce.edit'], 'commerce_editor_save');

    Livewire::actingAs($actor)
        ->test(EditPlacementPackage::class, ['record' => $package->id])
        ->fillForm([
            'price' => '49.00',
            'currency' => 'usd',
            'bump_allowed' => true,
            'bump_interval_hours' => 72,
            'free_bumps_per_period' => 2,
            'paid_bump_price' => '3.00',
            'translations' => ['en' => ['name' => 'Updated Name']],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $package->refresh();

    expect((float) $package->price)->toBe(49.0)
        ->and($package->currency)->toBe('USD') // dehydrateStateUsing derives the uppercase form
        ->and($package->bump_allowed)->toBeTrue()
        ->and($package->bump_interval_hours)->toBe(72)
        ->and($package->free_bumps_per_period)->toBe(2);

    $translationRows = DB::table('placement_package_translations')
        ->where('placement_package_id', $package->id)
        ->where('locale', 'en')
        ->get();

    expect($translationRows)->toHaveCount(1)
        ->and($translationRows->first()->name)->toBe('Updated Name');
});

it('refuses the placement package edit page to a commerce.view-only actor and admits a commerce.edit actor', function (): void {
    placementFinanceAdminPagesSeedLanguage();
    $package = placementFinanceAdminPagesPackage();

    $viewOnly = placementFinanceAdminPagesActor(['admin_panel_access', 'commerce.view'], 'commerce_view_only');
    test()->actingAs($viewOnly)
        ->get(PlacementPackageResource::getUrl('edit', ['record' => $package->id], panel: 'admin'))
        ->assertForbidden();

    $editor = placementFinanceAdminPagesActor(['admin_panel_access', 'commerce.view', 'commerce.edit'], 'commerce_edit_allowed');
    test()->actingAs($editor)
        ->get(PlacementPackageResource::getUrl('edit', ['record' => $package->id], panel: 'admin'))
        ->assertSuccessful();
});

// --- CreateFinancialRecord ------------------------------------------------

it('nulls the object foreign key for a banner-subject record even though the field is never submitted by the form', function (): void {
    $bannerId = placementFinanceAdminPagesBanner();
    $actor = placementFinanceAdminPagesActor(['admin_panel_access', 'finance.view', 'finance.create'], 'finance_creator');

    Livewire::actingAs($actor)
        ->test(CreateFinancialRecord::class)
        ->fillForm([
            'subject_kind' => 'banner',
            'banner_id' => $bannerId,
            'service' => 'advertising',
            'amount' => '15.00',
            'currency' => 'eur',
            'status' => 'paid',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $record = FinancialRecord::query()->where('banner_id', $bannerId)->firstOrFail();

    expect($record->object_id)->toBeNull()
        ->and($record->currency)->toBe('EUR')
        ->and((float) $record->amount)->toBe(15.0);
});

it('refuses the financial record create page to a finance.view-only actor and admits a finance.create actor', function (): void {
    $viewOnly = placementFinanceAdminPagesActor(['admin_panel_access', 'finance.view'], 'finance_view_only_create');
    test()->actingAs($viewOnly)
        ->get(FinancialRecordResource::getUrl('create', panel: 'admin'))
        ->assertForbidden();

    $creator = placementFinanceAdminPagesActor(['admin_panel_access', 'finance.view', 'finance.create'], 'finance_create_allowed');
    test()->actingAs($creator)
        ->get(FinancialRecordResource::getUrl('create', panel: 'admin'))
        ->assertSuccessful();
});

// --- EditFinancialRecord ---------------------------------------------------

it('derives subject_kind from whichever foreign key the stored record actually carries', function (): void {
    $objectId = placementFinanceAdminPagesObject();
    $bannerId = placementFinanceAdminPagesBanner();

    /** @var FinancialRecord $objectRecord */
    $objectRecord = FinancialRecord::query()->create([
        'object_id' => $objectId, 'service' => 'placement', 'amount' => '10.00',
        'currency' => 'EUR', 'status' => 'paid',
    ]);
    /** @var FinancialRecord $bannerRecord */
    $bannerRecord = FinancialRecord::query()->create([
        'banner_id' => $bannerId, 'service' => 'advertising', 'amount' => '20.00',
        'currency' => 'EUR', 'status' => 'paid',
    ]);

    $actor = placementFinanceAdminPagesActor(['admin_panel_access', 'finance.view', 'finance.edit'], 'finance_editor_derive');

    Livewire::actingAs($actor)
        ->test(EditFinancialRecord::class, ['record' => $objectRecord->id])
        ->assertFormSet(['subject_kind' => 'object']);

    Livewire::actingAs($actor)
        ->test(EditFinancialRecord::class, ['record' => $bannerRecord->id])
        ->assertFormSet(['subject_kind' => 'banner']);
});

it('renders the edit form disabled, since financial_records is append-only and a real save would violate its own database trigger', function (): void {
    $objectId = placementFinanceAdminPagesObject();

    /** @var FinancialRecord $record */
    $record = FinancialRecord::query()->create([
        'object_id' => $objectId, 'service' => 'placement', 'amount' => '10.00',
        'currency' => 'EUR', 'status' => 'awaiting_payment',
    ]);

    $actor = placementFinanceAdminPagesActor(['admin_panel_access', 'finance.view', 'finance.edit'], 'finance_editor_view');

    // Confirms the bug this test was written to catch: before EditFinancialRecord
    // disabled its own form, submitting a changed value here reached a real
    // UPDATE and crashed on financial_records' enforce_append_only() trigger
    // with an uncaught QueryException — a page no administrator could actually
    // use. The schema being genuinely disabled (not merely "expected" to be
    // left alone) is what proves the fix, not just that this one call succeeds.
    $component = Livewire::actingAs($actor)
        ->test(EditFinancialRecord::class, ['record' => $record->id])
        ->fillForm(['status' => 'paid', 'amount' => '12.50'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($component->instance()->form->isDisabled())->toBeTrue();

    $record->refresh();

    expect($record->status)->toBe('awaiting_payment')
        ->and((float) $record->amount)->toBe(10.0);
});

it('offers only Cancel on the financial record edit page, never a Save action that would silently do nothing', function (): void {
    $objectId = placementFinanceAdminPagesObject();

    /** @var FinancialRecord $record */
    $record = FinancialRecord::query()->create([
        'object_id' => $objectId, 'service' => 'placement', 'amount' => '10.00',
        'currency' => 'EUR', 'status' => 'paid',
    ]);

    $actor = placementFinanceAdminPagesActor(['admin_panel_access', 'finance.view', 'finance.edit'], 'finance_editor_actions');

    // getFormActions() (the Save/Cancel pair at the bottom of the form) is
    // a separate rendering path from the mountable-action registry
    // assertActionExists() checks — the rendered labels are what a real
    // administrator actually sees, so that is what this asserts against.
    Livewire::actingAs($actor)
        ->test(EditFinancialRecord::class, ['record' => $record->id])
        ->assertSee(__('filament-panels::resources/pages/edit-record.form.actions.cancel.label'))
        ->assertDontSee(__('filament-panels::resources/pages/edit-record.form.actions.save.label'));
});

it('refuses the financial record edit page to a finance.view-only actor and admits a finance.edit actor', function (): void {
    $objectId = placementFinanceAdminPagesObject();
    /** @var FinancialRecord $record */
    $record = FinancialRecord::query()->create([
        'object_id' => $objectId, 'service' => 'placement', 'amount' => '10.00',
        'currency' => 'EUR', 'status' => 'paid',
    ]);

    $viewOnly = placementFinanceAdminPagesActor(['admin_panel_access', 'finance.view'], 'finance_view_only_edit');
    test()->actingAs($viewOnly)
        ->get(FinancialRecordResource::getUrl('edit', ['record' => $record->id], panel: 'admin'))
        ->assertForbidden();

    $editor = placementFinanceAdminPagesActor(['admin_panel_access', 'finance.view', 'finance.edit'], 'finance_edit_allowed');
    test()->actingAs($editor)
        ->get(FinancialRecordResource::getUrl('edit', ['record' => $record->id], panel: 'admin'))
        ->assertSuccessful();
});
