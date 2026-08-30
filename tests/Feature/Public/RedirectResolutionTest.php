<?php

declare(strict_types=1);

use App\Exceptions\SlugReuseRefusedException;
use App\Filament\Admin\Resources\Objects\Pages\EditObject;
use App\Filament\Admin\Resources\Redirects\Pages\ListRedirects;
use App\Filament\Admin\Resources\Territories\Pages\EditTerritory;
use App\Models\Object_;
use App\Models\Redirect;
use App\Models\Territory;
use App\Models\User;
use App\Services\Geography\TerritoryAdministrator;
use App\Services\Seo\RedirectRegistrar;
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
| Redirect Table — Model, Resolution, Same-Operation Creation
|--------------------------------------------------------------------------
|
| A changed slug leaves a permanent redirect from the old address, created
| in the same operation as the change itself — never a later, separate
| step, since a redirect added after the fact is added after the traffic
| to the old address has already been lost. A slug is never reused while a
| redirect still claims it.
|
*/

/** @return array{languageId: int, countryId: int} */
function redirectTestRegistry(): array
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

function redirectTestMakeTerritory(int $countryId, string $name): Territory
{
    $levelId = DB::table('territory_levels')->insertGetId([
        'country_id' => $countryId, 'depth_rank' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $territoryId = DB::table('territories')->insertGetId([
        'country_id' => $countryId, 'level_id' => $levelId, 'is_active' => true,
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

function redirectTestMakeType(): int
{
    $typeId = DB::table('object_types')->insertGetId([
        'key' => 'hotel', 'is_active' => true, 'has_rooms' => false, 'has_availability_status' => false,
        'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('object_type_translations')->insert([
        'object_type_id' => $typeId, 'locale' => 'en', 'name' => 'Hotel', 'slug' => 'hotel',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return $typeId;
}

function redirectTestMakeObject(int $countryId, int $territoryId, int $typeId, string $name): Object_
{
    $objectId = DB::table('objects')->insertGetId([
        'ulid' => (string) Str::ulid(), 'owner_id' => User::factory()->create()->id,
        'object_type_id' => $typeId, 'territory_id' => $territoryId, 'country_id' => $countryId,
        'status' => 'published', 'moderation_status' => 'approved',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('object_translations')->insert([
        'object_id' => $objectId, 'locale' => 'en', 'name' => $name,
        'slug' => Str::slug($name), 'needs_review' => false, 'published_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    /** @var Object_ $object */
    $object = Object_::query()->findOrFail($objectId);

    return $object;
}

function redirectTestActor(): User
{
    $permissions = [
        'admin_panel_access', 'settings.view', 'settings.edit',
        'geography.view', 'geography.edit', 'object.view', 'object.edit',
    ];

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $role = Role::findOrCreate('redirect_test_admin', 'web');
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

it('creates a redirect in the same operation as a territory slug edit, and the old path serves a 301', function (): void {
    $fixture = redirectTestRegistry();
    $territory = redirectTestMakeTerritory($fixture['countryId'], 'Original Slug');
    $actor = redirectTestActor();

    Livewire::actingAs($actor)
        ->test(EditTerritory::class, ['record' => $territory->id])
        ->fillForm(['translations.en.name' => 'Original Slug', 'translations.en.slug' => 'renamed-slug'])
        ->call('save')
        ->assertHasNoFormErrors();

    $redirect = Redirect::query()->where('locale', 'en')->where('from_path', 'md/original-slug')->first();

    expect($redirect)->not->toBeNull()
        ->and($redirect->to_path)->toBe('md/renamed-slug')
        ->and($redirect->is_active)->toBeTrue();

    $this->get('/en/md/original-slug')->assertRedirect('/en/md/renamed-slug');
    $this->get('/en/md/renamed-slug')->assertOk();
});

it('creates a redirect in the same operation as an object slug edit, and the old path serves a 301', function (): void {
    $fixture = redirectTestRegistry();
    $territory = redirectTestMakeTerritory($fixture['countryId'], 'Object Territory');
    $typeId = redirectTestMakeType();
    $object = redirectTestMakeObject($fixture['countryId'], $territory->id, $typeId, 'Original Hotel');
    $actor = redirectTestActor();

    Livewire::actingAs($actor)
        ->test(EditObject::class, ['record' => $object->id])
        ->fillForm(['translations.en.name' => 'Original Hotel', 'translations.en.slug' => 'renamed-hotel'])
        ->call('save')
        ->assertHasNoFormErrors();

    $redirect = Redirect::query()->where('locale', 'en')->where('from_path', 'o/original-hotel')->first();

    expect($redirect)->not->toBeNull()->and($redirect->to_path)->toBe('o/renamed-hotel');

    $this->get('/en/o/original-hotel')->assertRedirect('/en/o/renamed-hotel');
    $this->get('/en/o/renamed-hotel')->assertOk();
});

it('creates a redirect when a territory is reparented, covering every descendant path', function (): void {
    $fixture = redirectTestRegistry();
    $oldParent = redirectTestMakeTerritory($fixture['countryId'], 'Old Parent');
    $newParent = redirectTestMakeTerritory($fixture['countryId'], 'New Parent');
    $child = redirectTestMakeTerritory($fixture['countryId'], 'Child');

    DB::table('territories')->where('id', $child->id)->update(['parent_id' => $oldParent->id]);
    app(TerritoryAdministrator::class)->recomputeSlugPaths($child->fresh());

    $actor = redirectTestActor();
    app(TerritoryAdministrator::class)->reparent($child->fresh(), $newParent->id, $actor);

    $redirect = Redirect::query()->where('locale', 'en')->where('from_path', 'md/old-parent/child')->first();

    expect($redirect)->not->toBeNull()->and($redirect->to_path)->toBe('md/new-parent/child');

    $this->get('/en/md/old-parent/child')->assertRedirect('/en/md/new-parent/child');
});

it('never reuses a slug an active redirect still claims', function (): void {
    $fixture = redirectTestRegistry();
    $territoryA = redirectTestMakeTerritory($fixture['countryId'], 'Territory A');
    $territoryB = redirectTestMakeTerritory($fixture['countryId'], 'Territory B');
    $actor = redirectTestActor();

    // A's slug changes, claiming a redirect from "territory-a".
    Livewire::actingAs($actor)
        ->test(EditTerritory::class, ['record' => $territoryA->id])
        ->fillForm(['translations.en.name' => 'Territory A', 'translations.en.slug' => 'moved-away'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(RedirectRegistrar::class)->isClaimed('en', 'md/territory-a'))->toBeTrue();

    // B may not now claim the exact path the redirect still promises leads
    // elsewhere — the slug column is written directly (not through the
    // form) so recomputeSlugPaths()'s own fresh query sees it, matching
    // exactly what an administrator's saved form input would leave behind.
    DB::table('territory_translations')
        ->where('territory_id', $territoryB->id)->where('locale', 'en')
        ->update(['slug' => 'territory-a']);

    app(TerritoryAdministrator::class)->recomputeSlugPaths($territoryB->fresh());
})->throws(SlugReuseRefusedException::class);

it('collapses a redirect chain to a single hop when the target itself moves again', function (): void {
    $fixture = redirectTestRegistry();
    $territory = redirectTestMakeTerritory($fixture['countryId'], 'Chain Start');
    $actor = redirectTestActor();

    Livewire::actingAs($actor)
        ->test(EditTerritory::class, ['record' => $territory->id])
        ->fillForm(['translations.en.name' => 'Chain Start', 'translations.en.slug' => 'chain-middle'])
        ->call('save')
        ->assertHasNoFormErrors();

    Livewire::actingAs($actor)
        ->test(EditTerritory::class, ['record' => $territory->id])
        ->fillForm(['translations.en.name' => 'Chain Start', 'translations.en.slug' => 'chain-end'])
        ->call('save')
        ->assertHasNoFormErrors();

    $first = Redirect::query()->where('locale', 'en')->where('from_path', 'md/chain-start')->first();
    $second = Redirect::query()->where('locale', 'en')->where('from_path', 'md/chain-middle')->first();

    expect($first)->not->toBeNull()->and($first->to_path)->toBe('md/chain-end')
        ->and($second)->not->toBeNull()->and($second->to_path)->toBe('md/chain-end');

    $this->get('/en/md/chain-start')->assertRedirect('/en/md/chain-end');
});

it('reaches the redirect administration screen and lists a created redirect', function (): void {
    redirectTestRegistry();
    $actor = redirectTestActor();

    Redirect::query()->create([
        'locale' => 'en', 'from_path' => 'o/old', 'to_path' => 'o/new', 'is_active' => true,
    ]);

    Livewire::actingAs($actor)
        ->test(ListRedirects::class)
        ->assertOk()
        ->assertCanSeeTableRecords(Redirect::all());
});
