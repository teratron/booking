<?php

declare(strict_types=1);

use App\Models\Territory;
use App\Models\User;
use App\Services\Geography\TerritoryAdministrator;
use App\Services\Seo\RedirectRegistrar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Redirect Permanence & Slug Stability — the Durable Invariant
|--------------------------------------------------------------------------
|
| RedirectResolutionTest (T-6A02) proves each mechanism works in isolation;
| this file proves the invariant holds as a durable guarantee, holistically,
| for the one case that mechanism-level test does not reach: a mid-tree
| reparent whose descendant is itself several levels deep, where "every
| affected descendant path" has to mean more than one directly-attached
| child.
|
*/

/** @return array{languageId: int, countryId: int} */
function slugStabilityRegistry(): array
{
    $languageId = DB::table('languages')->insertGetId([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $countryId = DB::table('countries')->insertGetId([
        'code' => 'MD', 'currency' => 'MDL', 'phone_code' => '+373',
        'primary_language_id' => $languageId, 'is_active' => true, 'display_order' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return compact('languageId', 'countryId');
}

function slugStabilityMakeTerritory(int $countryId, string $name, ?int $parentId = null): Territory
{
    $levelId = DB::table('territory_levels')->insertGetId([
        'country_id' => $countryId, 'depth_rank' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $territoryId = DB::table('territories')->insertGetId([
        'country_id' => $countryId, 'level_id' => $levelId, 'parent_id' => $parentId, 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $slug = Str::slug($name);
    DB::table('territory_translations')->insert([
        'territory_id' => $territoryId, 'country_id' => $countryId, 'locale' => 'en', 'name' => $name, 'slug' => $slug,
        'full_slug_path' => $slug,
        'needs_review' => false, 'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);

    /** @var Territory $territory */
    $territory = Territory::query()->findOrFail($territoryId);

    return $territory;
}

function slugStabilityActor(): User
{
    $permissions = ['admin_panel_access', 'geography.view', 'geography.edit'];

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $role = Role::findOrCreate('slug_stability_admin', 'web');
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

it('redirects every descendant path when a mid-tree territory with its own children is reparented', function (): void {
    $fixture = slugStabilityRegistry();
    $actor = slugStabilityActor();

    // grandOld → mid → leaf, three levels; reparenting "mid" under a new
    // grandparent must leave a permanent redirect not only for "mid" itself
    // but for "leaf", whose own compound path changes purely as a
    // consequence of its ancestor moving — it was never touched directly.
    $grandOld = slugStabilityMakeTerritory($fixture['countryId'], 'Grand Old');
    $grandNew = slugStabilityMakeTerritory($fixture['countryId'], 'Grand New');
    $mid = slugStabilityMakeTerritory($fixture['countryId'], 'Mid Territory', $grandOld->id);
    $leaf = slugStabilityMakeTerritory($fixture['countryId'], 'Leaf Territory', $mid->id);

    app(TerritoryAdministrator::class)->recomputeSlugPaths($mid->fresh());
    app(TerritoryAdministrator::class)->recomputeSlugPaths($leaf->fresh());

    app(TerritoryAdministrator::class)->reparent($mid->fresh(), $grandNew->id, $actor);

    $midRedirect = DB::table('redirects')->where('locale', 'en')
        ->where('from_path', 'md/grand-old/mid-territory')->first();
    $leafRedirect = DB::table('redirects')->where('locale', 'en')
        ->where('from_path', 'md/grand-old/mid-territory/leaf-territory')->first();

    expect($midRedirect)->not->toBeNull()
        ->and($midRedirect->to_path)->toBe('md/grand-new/mid-territory')
        ->and($leafRedirect)->not->toBeNull()
        ->and($leafRedirect->to_path)->toBe('md/grand-new/mid-territory/leaf-territory');

    $this->get('/en/md/grand-old/mid-territory')->assertRedirect('/en/md/grand-new/mid-territory');
    $this->get('/en/md/grand-old/mid-territory/leaf-territory')
        ->assertRedirect('/en/md/grand-new/mid-territory/leaf-territory');
});

it('collapses a two-hop rename chain to a single redirect, never a loop', function (): void {
    $fixture = slugStabilityRegistry();
    $territory = slugStabilityMakeTerritory($fixture['countryId'], 'Chain Origin');
    app(TerritoryAdministrator::class)->recomputeSlugPaths($territory->fresh());

    DB::table('territory_translations')->where('territory_id', $territory->id)->where('locale', 'en')
        ->update(['slug' => 'chain-relay']);
    app(TerritoryAdministrator::class)->recomputeSlugPaths($territory->fresh());

    DB::table('territory_translations')->where('territory_id', $territory->id)->where('locale', 'en')
        ->update(['slug' => 'chain-destination']);
    app(TerritoryAdministrator::class)->recomputeSlugPaths($territory->fresh());

    $origin = DB::table('redirects')->where('locale', 'en')->where('from_path', 'md/chain-origin')->first();
    $relay = DB::table('redirects')->where('locale', 'en')->where('from_path', 'md/chain-relay')->first();

    // Both redirects point straight at the final destination — never at
    // each other — which is what "collapsed, not chained" means: following
    // either one is a single hop, and neither can loop back into the other.
    expect($origin->to_path)->toBe('md/chain-destination')
        ->and($relay->to_path)->toBe('md/chain-destination');

    $this->get('/en/md/chain-origin')->assertRedirect('/en/md/chain-destination');
    $this->get('/en/md/chain-relay')->assertRedirect('/en/md/chain-destination');
});

it('never lets a new territory claim a slug an active redirect still promises leads elsewhere', function (): void {
    $fixture = slugStabilityRegistry();
    $moved = slugStabilityMakeTerritory($fixture['countryId'], 'Claimed Slug');
    app(TerritoryAdministrator::class)->recomputeSlugPaths($moved->fresh());

    DB::table('territory_translations')->where('territory_id', $moved->id)->where('locale', 'en')
        ->update(['slug' => 'moved-elsewhere']);
    app(TerritoryAdministrator::class)->recomputeSlugPaths($moved->fresh());

    expect(app(RedirectRegistrar::class)->isClaimed('en', 'md/claimed-slug'))->toBeTrue();
});
