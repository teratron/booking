<?php

declare(strict_types=1);

use App\Models\CatalogFilterPromotion;
use App\Models\Language;
use App\Models\Module;
use App\Models\SeoMetadataTemplate;
use App\Models\User;
use App\Policies\CatalogFilterPromotionPolicy;
use App\Policies\LanguagePolicy;
use App\Policies\ModulePolicy;
use App\Policies\SeoMetadataTemplatePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Portal-Wide Registry Policies
|--------------------------------------------------------------------------
|
| Four registries share one shape: no country, territory, or category axis
| narrows them, so each ability resolves from a single permission the acting
| user's role either grants or does not — CatalogFilterPromotionPolicy and
| SeoMetadataTemplatePolicy on seo.view/seo.edit, LanguagePolicy and
| ModulePolicy on settings.view/settings.edit. ModulePolicy's create and
| delete are the one deliberate exception: membership in the module registry
| is fixed by the code that implements each module, so both abilities are
| denied outright regardless of what the acting user holds.
|
*/

function scopedRegistryPolicyActor(array $permissions, string $roleKey): User
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

// -----------------------------------------------------------------------
// CatalogFilterPromotionPolicy — seo.view / seo.edit.
// -----------------------------------------------------------------------

it('resolves every CatalogFilterPromotionPolicy action from the acting user\'s own seo.* grants', function (): void {
    $promotion = CatalogFilterPromotion::query()->create([
        'signature' => 'type=3',
        'is_active' => true,
    ]);

    $permitted = scopedRegistryPolicyActor(
        ['admin_panel_access', 'seo.view', 'seo.edit'],
        'catalog_filter_promotion_permitted',
    );
    $refused = scopedRegistryPolicyActor(['admin_panel_access'], 'catalog_filter_promotion_refused');

    $policy = app(CatalogFilterPromotionPolicy::class);

    expect($policy->viewAny($permitted))->toBeTrue()
        ->and($policy->view($permitted, $promotion))->toBeTrue()
        ->and($policy->create($permitted))->toBeTrue()
        ->and($policy->update($permitted, $promotion))->toBeTrue()
        ->and($policy->delete($permitted, $promotion))->toBeTrue();

    expect($policy->viewAny($refused))->toBeFalse()
        ->and($policy->view($refused, $promotion))->toBeFalse()
        ->and($policy->create($refused))->toBeFalse()
        ->and($policy->update($refused, $promotion))->toBeFalse()
        ->and($policy->delete($refused, $promotion))->toBeFalse();
});

it('grants CatalogFilterPromotionPolicy read access on seo.view alone, without also granting the write abilities', function (): void {
    $promotion = CatalogFilterPromotion::query()->create([
        'signature' => 'territory=7',
        'is_active' => true,
    ]);

    $readOnly = scopedRegistryPolicyActor(['admin_panel_access', 'seo.view'], 'catalog_filter_promotion_read_only');

    $policy = app(CatalogFilterPromotionPolicy::class);

    expect($policy->viewAny($readOnly))->toBeTrue()
        ->and($policy->view($readOnly, $promotion))->toBeTrue()
        ->and($policy->create($readOnly))->toBeFalse()
        ->and($policy->update($readOnly, $promotion))->toBeFalse()
        ->and($policy->delete($readOnly, $promotion))->toBeFalse();
});

// -----------------------------------------------------------------------
// SeoMetadataTemplatePolicy — seo.view / seo.edit.
// -----------------------------------------------------------------------

it('resolves every SeoMetadataTemplatePolicy action from the acting user\'s own seo.* grants', function (): void {
    DB::table('languages')->insert([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $template = SeoMetadataTemplate::query()->create([
        'entity_type' => 'object_type',
        'locale' => 'en',
        'field' => 'seo_title',
        'template' => '{name} in {territory}',
    ]);

    $permitted = scopedRegistryPolicyActor(
        ['admin_panel_access', 'seo.view', 'seo.edit'],
        'seo_metadata_template_permitted',
    );
    $refused = scopedRegistryPolicyActor(['admin_panel_access'], 'seo_metadata_template_refused');

    $policy = app(SeoMetadataTemplatePolicy::class);

    expect($policy->viewAny($permitted))->toBeTrue()
        ->and($policy->view($permitted, $template))->toBeTrue()
        ->and($policy->create($permitted))->toBeTrue()
        ->and($policy->update($permitted, $template))->toBeTrue()
        ->and($policy->delete($permitted, $template))->toBeTrue();

    expect($policy->viewAny($refused))->toBeFalse()
        ->and($policy->view($refused, $template))->toBeFalse()
        ->and($policy->create($refused))->toBeFalse()
        ->and($policy->update($refused, $template))->toBeFalse()
        ->and($policy->delete($refused, $template))->toBeFalse();
});

it('grants SeoMetadataTemplatePolicy read access on seo.view alone, without also granting the write abilities', function (): void {
    DB::table('languages')->insert([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $template = SeoMetadataTemplate::query()->create([
        'entity_type' => 'object_type',
        'locale' => 'en',
        'field' => 'seo_description',
        'template' => 'Discover {name}',
    ]);

    $readOnly = scopedRegistryPolicyActor(['admin_panel_access', 'seo.view'], 'seo_metadata_template_read_only');

    $policy = app(SeoMetadataTemplatePolicy::class);

    expect($policy->viewAny($readOnly))->toBeTrue()
        ->and($policy->view($readOnly, $template))->toBeTrue()
        ->and($policy->create($readOnly))->toBeFalse()
        ->and($policy->update($readOnly, $template))->toBeFalse()
        ->and($policy->delete($readOnly, $template))->toBeFalse();
});

// -----------------------------------------------------------------------
// LanguagePolicy — settings.view / settings.edit, including reorder.
// -----------------------------------------------------------------------

it('resolves every LanguagePolicy action, including reorder, from the acting user\'s own settings.* grants', function (): void {
    $language = Language::query()->create([
        'code' => 'ro', 'short_label' => 'RO', 'text_direction' => 'ltr',
        'is_active' => false, 'is_primary' => false, 'display_order' => 3,
    ]);

    $permitted = scopedRegistryPolicyActor(
        ['admin_panel_access', 'settings.view', 'settings.edit'],
        'language_policy_permitted',
    );
    $refused = scopedRegistryPolicyActor(['admin_panel_access'], 'language_policy_refused');

    $policy = app(LanguagePolicy::class);

    expect($policy->viewAny($permitted))->toBeTrue()
        ->and($policy->view($permitted, $language))->toBeTrue()
        ->and($policy->create($permitted))->toBeTrue()
        ->and($policy->update($permitted, $language))->toBeTrue()
        ->and($policy->delete($permitted, $language))->toBeTrue()
        ->and($policy->reorder($permitted))->toBeTrue();

    expect($policy->viewAny($refused))->toBeFalse()
        ->and($policy->view($refused, $language))->toBeFalse()
        ->and($policy->create($refused))->toBeFalse()
        ->and($policy->update($refused, $language))->toBeFalse()
        ->and($policy->delete($refused, $language))->toBeFalse()
        ->and($policy->reorder($refused))->toBeFalse();
});

it('grants LanguagePolicy read access on settings.view alone, without also granting the write abilities', function (): void {
    $language = Language::query()->create([
        'code' => 'uk', 'short_label' => 'UK', 'text_direction' => 'ltr',
        'is_active' => false, 'is_primary' => false, 'display_order' => 4,
    ]);

    $readOnly = scopedRegistryPolicyActor(['admin_panel_access', 'settings.view'], 'language_policy_read_only');

    $policy = app(LanguagePolicy::class);

    expect($policy->viewAny($readOnly))->toBeTrue()
        ->and($policy->view($readOnly, $language))->toBeTrue()
        ->and($policy->create($readOnly))->toBeFalse()
        ->and($policy->update($readOnly, $language))->toBeFalse()
        ->and($policy->delete($readOnly, $language))->toBeFalse()
        ->and($policy->reorder($readOnly))->toBeFalse();
});

// -----------------------------------------------------------------------
// ModulePolicy — settings.view / settings.edit for view and update; create
// and delete are hard-denied regardless of permission, since a module's
// existence is fixed by the code that implements it, not by an
// administrator's grant.
// -----------------------------------------------------------------------

it('resolves ModulePolicy view and update from settings.* grants, and denies create/delete outright to the same fully-permitted actor', function (): void {
    $module = Module::query()->create([
        'key' => 'payment',
        'default_state' => 'disabled',
        'scopable_levels' => ['portal', 'country'],
        'is_active' => true,
    ]);

    $permitted = scopedRegistryPolicyActor(
        ['admin_panel_access', 'settings.view', 'settings.edit'],
        'module_policy_permitted',
    );
    $refused = scopedRegistryPolicyActor(['admin_panel_access'], 'module_policy_refused');

    $policy = app(ModulePolicy::class);

    expect($policy->viewAny($permitted))->toBeTrue()
        ->and($policy->view($permitted, $module))->toBeTrue()
        ->and($policy->update($permitted, $module))->toBeTrue();

    expect($policy->viewAny($refused))->toBeFalse()
        ->and($policy->view($refused, $module))->toBeFalse()
        ->and($policy->update($refused, $module))->toBeFalse();

    // Hard-denied for both actors alike — holding settings.edit changes
    // nothing here, since neither ability consults a permission at all.
    expect($policy->create($permitted))->toBeFalse()
        ->and($policy->delete($permitted, $module))->toBeFalse()
        ->and($policy->create($refused))->toBeFalse()
        ->and($policy->delete($refused, $module))->toBeFalse();
});

it('grants ModulePolicy read access on settings.view alone, without also granting update', function (): void {
    $module = Module::query()->create([
        'key' => 'booking',
        'default_state' => 'disabled',
        'scopable_levels' => ['portal', 'country', 'category', 'owner', 'object'],
        'is_active' => true,
    ]);

    $readOnly = scopedRegistryPolicyActor(['admin_panel_access', 'settings.view'], 'module_policy_read_only');

    $policy = app(ModulePolicy::class);

    expect($policy->viewAny($readOnly))->toBeTrue()
        ->and($policy->view($readOnly, $module))->toBeTrue()
        ->and($policy->update($readOnly, $module))->toBeFalse();
});
