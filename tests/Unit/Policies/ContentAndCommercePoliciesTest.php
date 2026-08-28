<?php

declare(strict_types=1);

use App\Models\ArticleCategory;
use App\Models\FinancialRecord;
use App\Models\PlacementTier;
use App\Models\User;
use App\Policies\ArticleCategoryPolicy;
use App\Policies\FinancialRecordPolicy;
use App\Policies\PlacementTierPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| ArticleCategoryPolicy / FinancialRecordPolicy / PlacementTierPolicy
|--------------------------------------------------------------------------
|
| All three are the flat ScopedPolicy shape: viewAny/view/create/update/
| delete resolved from a single permission string, with no scope value
| ever passed to authorize() — the editorial taxonomy, the ledger, and the
| tier registry are each portal-wide configuration per their own
| docblocks, not narrowed by country, territory, or category. That "no
| scope axis" claim is only true if a scoped (non-`none`) grant of the
| right permission still fails to authorize here, since the policy never
| forwards a country/territory/category value for ScopeAuthorizer to
| match against — proven below, not just asserted in prose.
|
*/

/** @param  list<string>  $permissions */
function contentAndCommercePolicyActor(
    array $permissions,
    string $roleKey,
    string $scopeKind = 'none',
    ?int $scopeReferenceId = null,
): User {
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
        'scope_kind' => $scopeKind, 'scope_reference_id' => $scopeReferenceId,
        'granted_by' => $user->id, 'granted_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return $user->fresh();
}

function contentAndCommercePolicyCategory(string $slug = 'guides'): ArticleCategory
{
    return ArticleCategory::query()->create(['slug' => $slug, 'is_active' => true]);
}

function contentAndCommercePolicyFinancialRecord(): FinancialRecord
{
    $languageId = DB::table('languages')->insertGetId([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $countryId = DB::table('countries')->insertGetId([
        'code' => 'MD', 'currency' => 'MDL', 'phone_code' => '+373',
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
        'key' => 'hotel', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $owner = User::factory()->create();
    $objectId = DB::table('objects')->insertGetId([
        'ulid' => (string) Str::ulid(), 'owner_id' => $owner->id,
        'object_type_id' => $typeId, 'territory_id' => $territoryId, 'country_id' => $countryId,
        'status' => 'published', 'moderation_status' => 'approved',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    /** @var FinancialRecord $record */
    $record = FinancialRecord::query()->create([
        'object_id' => $objectId,
        'service' => 'placement',
        'amount' => '10.00',
        'currency' => 'EUR',
        'status' => 'paid',
    ]);

    return $record;
}

function contentAndCommercePolicyTier(): PlacementTier
{
    $nextRank = 1 + (int) DB::table('placement_tiers')->max('rank');

    $tierId = DB::table('placement_tiers')->insertGetId([
        'rank' => $nextRank, 'border_colour' => '#000000', 'badge_colour' => '#111111',
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return PlacementTier::query()->findOrFail($tierId);
}

// --- ArticleCategoryPolicy ---------------------------------------------

it('resolves every ArticleCategoryPolicy action from the acting user\'s own content.* grants', function (): void {
    $category = contentAndCommercePolicyCategory();

    $permitted = contentAndCommercePolicyActor(
        ['admin_panel_access', 'content.view', 'content.create', 'content.edit', 'content.delete'],
        'article_category_policy_permitted',
    );
    $refused = User::factory()->create();

    $policy = app(ArticleCategoryPolicy::class);

    expect($policy->viewAny($permitted))->toBeTrue()
        ->and($policy->view($permitted, $category))->toBeTrue()
        ->and($policy->create($permitted))->toBeTrue()
        ->and($policy->update($permitted, $category))->toBeTrue()
        ->and($policy->delete($permitted, $category))->toBeTrue();

    expect($policy->viewAny($refused))->toBeFalse()
        ->and($policy->view($refused, $category))->toBeFalse()
        ->and($policy->create($refused))->toBeFalse()
        ->and($policy->update($refused, $category))->toBeFalse()
        ->and($policy->delete($refused, $category))->toBeFalse();
});

it('grants ArticleCategoryPolicy read access on content.view alone, without also granting the write abilities', function (): void {
    $category = contentAndCommercePolicyCategory('read-only-guides');

    $readOnly = contentAndCommercePolicyActor(
        ['admin_panel_access', 'content.view'],
        'article_category_policy_read_only',
    );

    $policy = app(ArticleCategoryPolicy::class);

    expect($policy->viewAny($readOnly))->toBeTrue()
        ->and($policy->view($readOnly, $category))->toBeTrue()
        ->and($policy->create($readOnly))->toBeFalse()
        ->and($policy->update($readOnly, $category))->toBeFalse()
        ->and($policy->delete($readOnly, $category))->toBeFalse();
});

it('denies ArticleCategoryPolicy despite a content.view grant when that grant is country-scoped, since the taxonomy has no scope axis', function (): void {
    $category = contentAndCommercePolicyCategory('scoped-guides');

    // The permission exists and is assigned — spatie's own can() would say
    // yes — but the policy never forwards a country/territory/category
    // value to ScopeAuthorizer, so only a `none`-kind grant ever matches.
    $countryScoped = contentAndCommercePolicyActor(
        ['admin_panel_access', 'content.view'],
        'article_category_policy_country_scoped',
        scopeKind: 'country',
        scopeReferenceId: 1,
    );

    expect($countryScoped->can('content.view'))->toBeTrue();

    $policy = app(ArticleCategoryPolicy::class);

    expect($policy->viewAny($countryScoped))->toBeFalse()
        ->and($policy->view($countryScoped, $category))->toBeFalse();
});

// --- FinancialRecordPolicy ----------------------------------------------

it('resolves every FinancialRecordPolicy action from the acting user\'s own finance.* grants', function (): void {
    $record = contentAndCommercePolicyFinancialRecord();

    $permitted = contentAndCommercePolicyActor(
        ['admin_panel_access', 'finance.view', 'finance.create', 'finance.edit', 'finance.delete'],
        'financial_record_policy_permitted',
    );
    $refused = User::factory()->create();

    $policy = app(FinancialRecordPolicy::class);

    expect($policy->viewAny($permitted))->toBeTrue()
        ->and($policy->view($permitted, $record))->toBeTrue()
        ->and($policy->create($permitted))->toBeTrue()
        ->and($policy->update($permitted, $record))->toBeTrue()
        ->and($policy->delete($permitted, $record))->toBeTrue();

    expect($policy->viewAny($refused))->toBeFalse()
        ->and($policy->view($refused, $record))->toBeFalse()
        ->and($policy->create($refused))->toBeFalse()
        ->and($policy->update($refused, $record))->toBeFalse()
        ->and($policy->delete($refused, $record))->toBeFalse();
});

it('grants FinancialRecordPolicy read access on finance.view alone, without also granting the write abilities', function (): void {
    $record = contentAndCommercePolicyFinancialRecord();

    $readOnly = contentAndCommercePolicyActor(
        ['admin_panel_access', 'finance.view'],
        'financial_record_policy_read_only',
    );

    $policy = app(FinancialRecordPolicy::class);

    expect($policy->viewAny($readOnly))->toBeTrue()
        ->and($policy->view($readOnly, $record))->toBeTrue()
        ->and($policy->create($readOnly))->toBeFalse()
        ->and($policy->update($readOnly, $record))->toBeFalse()
        ->and($policy->delete($readOnly, $record))->toBeFalse();
});

it('denies FinancialRecordPolicy despite a finance.edit grant when that grant is object-category-scoped, since the ledger is never inherited from object scope', function (): void {
    $record = contentAndCommercePolicyFinancialRecord();

    $categoryScoped = contentAndCommercePolicyActor(
        ['admin_panel_access', 'finance.edit'],
        'financial_record_policy_category_scoped',
        scopeKind: 'category',
        scopeReferenceId: 1,
    );

    expect($categoryScoped->can('finance.edit'))->toBeTrue();

    $policy = app(FinancialRecordPolicy::class);

    expect($policy->update($categoryScoped, $record))->toBeFalse();
});

// --- PlacementTierPolicy --------------------------------------------------

it('resolves every PlacementTierPolicy action from the acting user\'s own commerce.* grants', function (): void {
    $tier = contentAndCommercePolicyTier();

    $permitted = contentAndCommercePolicyActor(
        ['admin_panel_access', 'commerce.view', 'commerce.edit'],
        'placement_tier_policy_permitted',
    );
    $refused = User::factory()->create();

    $policy = app(PlacementTierPolicy::class);

    expect($policy->viewAny($permitted))->toBeTrue()
        ->and($policy->view($permitted, $tier))->toBeTrue()
        ->and($policy->update($permitted, $tier))->toBeTrue();

    expect($policy->viewAny($refused))->toBeFalse()
        ->and($policy->view($refused, $tier))->toBeFalse()
        ->and($policy->update($refused, $tier))->toBeFalse();
});

it('grants PlacementTierPolicy read access on commerce.view alone, without also granting update', function (): void {
    $tier = contentAndCommercePolicyTier();

    $readOnly = contentAndCommercePolicyActor(
        ['admin_panel_access', 'commerce.view'],
        'placement_tier_policy_read_only',
    );

    $policy = app(PlacementTierPolicy::class);

    expect($policy->viewAny($readOnly))->toBeTrue()
        ->and($policy->view($readOnly, $tier))->toBeTrue()
        ->and($policy->update($readOnly, $tier))->toBeFalse();
});

it('never declares create or delete abilities on PlacementTierPolicy — the four ranks are structural, not administrator-created data', function (): void {
    $policy = app(PlacementTierPolicy::class);

    expect(method_exists($policy, 'create'))->toBeFalse()
        ->and(method_exists($policy, 'delete'))->toBeFalse();
});
