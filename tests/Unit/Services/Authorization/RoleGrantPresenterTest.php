<?php

declare(strict_types=1);

use App\Models\Country;
use App\Models\ObjectType;
use App\Models\RoleScope;
use App\Models\Territory;
use App\Models\User;
use App\Services\Authorization\RoleGrantPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Role Grant Presenter
|--------------------------------------------------------------------------
|
| tests/Feature/Admin/StaffAdministrationTest.php only exercises this
| presenter through the Filament staff list — one role, one country scope,
| never revoked. This file instantiates it directly to reach the branches
| that never surface there: allGrantLines() including a revoked grant,
| roleOptions()'s exclusion list, scopeDescription() for every scope kind
| (including a scope target deleted after the grant was made), and the
| per-instance memoization the class relies on to stay eager-load-safe.
|
*/

function rgpSeedLanguage(): int
{
    $existingId = DB::table('languages')->where('code', 'en')->value('id');

    if ($existingId !== null) {
        return (int) $existingId;
    }

    return DB::table('languages')->insertGetId([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function rgpSeedRole(string $key, ?string $displayName = null): Role
{
    $role = Role::create(['name' => $key, 'guard_name' => 'web']);

    if ($displayName !== null) {
        rgpSeedLanguage();

        DB::table('role_translations')->insert([
            'role_id' => $role->id, 'locale' => 'en', 'display_name' => $displayName,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    return $role;
}

function rgpSeedCountry(int $languageId, string $code = 'MD'): Country
{
    return Country::create([
        'code' => $code,
        'currency' => 'MDL',
        'phone_code' => '+373',
        'primary_language_id' => $languageId,
        'is_active' => true,
    ]);
}

function rgpSeedTerritory(int $countryId, ?string $name): Territory
{
    $levelId = DB::table('territory_levels')->insertGetId([
        'country_id' => $countryId, 'depth_rank' => 1, 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $territory = Territory::create([
        'country_id' => $countryId,
        'level_id' => $levelId,
        'is_active' => true,
    ]);

    if ($name !== null) {
        rgpSeedLanguage();

        DB::table('territory_translations')->insert([
            'territory_id' => $territory->id, 'country_id' => $countryId, 'locale' => 'en', 'name' => $name,
            'slug' => Str::slug($name).'-'.$territory->id,
            'full_slug_path' => Str::slug($name).'-'.$territory->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    return $territory;
}

function rgpSeedObjectType(string $key, ?string $name): ObjectType
{
    $objectType = ObjectType::create([
        'key' => $key,
        'is_active' => true,
    ]);

    if ($name !== null) {
        rgpSeedLanguage();

        DB::table('object_type_translations')->insert([
            'object_type_id' => $objectType->id, 'locale' => 'en', 'name' => $name,
            'slug' => Str::slug($name).'-'.$objectType->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    return $objectType;
}

function rgpGrant(
    User $user,
    Role $role,
    User $grantedBy,
    string $scopeKind = 'none',
    ?int $scopeReferenceId = null,
    ?User $revokedBy = null,
    ?Carbon $revokedAt = null,
): RoleScope {
    return RoleScope::create([
        'user_id' => $user->id,
        'role_id' => $role->id,
        'scope_kind' => $scopeKind,
        'scope_reference_id' => $scopeReferenceId,
        'granted_by' => $grantedBy->id,
        'granted_at' => now(),
        'revoked_by' => $revokedBy?->id,
        'revoked_at' => $revokedAt,
    ]);
}

it('lists every grant a user has ever held with the revoked one marked, while the active view excludes it', function (): void {
    $moderator = rgpSeedRole('moderator', 'Moderator');
    $countryAdministrator = rgpSeedRole('country_administrator', 'Country administrator');
    $actor = User::factory()->create();
    $staff = User::factory()->create();

    rgpGrant($staff, $moderator, $actor);
    rgpGrant($staff, $countryAdministrator, $actor, revokedBy: $actor, revokedAt: now()->subDay());

    $staff->load('roleScopes.role');
    $presenter = new RoleGrantPresenter;

    $noneLabel = (string) __('panel.staff.scope.none');
    $activeLine = "Moderator ({$noneLabel})";
    $revokedLine = (string) __('panel.staff.grants.revoked_line', [
        'grant' => "Country administrator ({$noneLabel})",
    ]);

    expect($presenter->activeGrantLines($staff))->toBe([$activeLine])
        // Sorted by the role's raw key ('country_administrator' before
        // 'moderator'), so the revoked line — still present here — comes first.
        ->and($presenter->allGrantLines($staff))->toBe([$revokedLine, $activeLine]);
});

it('returns an empty line list for a user who has never held a grant', function (): void {
    $staff = User::factory()->create();
    $staff->load('roleScopes.role');

    $presenter = new RoleGrantPresenter;

    expect($presenter->activeGrantLines($staff))->toBe([])
        ->and($presenter->allGrantLines($staff))->toBe([]);
});

it('offers role options keyed by role id with translated names, excluding the given role keys', function (): void {
    $moderator = rgpSeedRole('moderator', 'Moderator');
    $countryAdministrator = rgpSeedRole('country_administrator', 'Country administrator');
    $objectOwner = rgpSeedRole('object_owner', 'Object owner');

    $presenter = new RoleGrantPresenter;

    $options = $presenter->roleOptions(['object_owner']);

    // Ordered by the underlying role key, alphabetically:
    // 'country_administrator' before 'moderator', 'object_owner' excluded entirely.
    expect($options)->toBe([
        $countryAdministrator->id => 'Country administrator',
        $moderator->id => 'Moderator',
    ]);

    // Exclusion, not a separate suppression: an unlisted role key still surfaces.
    expect(array_key_exists($objectOwner->id, $options))->toBeFalse();
});

it('falls back to the raw role key when a role has no translation for the current locale', function (): void {
    $untranslated = rgpSeedRole('a_future_panel_role');

    $presenter = new RoleGrantPresenter;

    expect($presenter->roleName($untranslated->id, 'a_future_panel_role'))->toBe('a_future_panel_role')
        ->and($presenter->roleOptions())->toBe([$untranslated->id => 'a_future_panel_role']);
});

it('describes an unrestricted grant as "None" whether the scope kind is none or the reference id alone is missing', function (): void {
    $presenter = new RoleGrantPresenter;
    $noneLabel = (string) __('panel.staff.scope.none');

    expect($presenter->scopeDescription('none', null))->toBe($noneLabel)
        ->and($presenter->scopeDescription('none', 123))->toBe($noneLabel)
        ->and($presenter->scopeDescription('country', null))->toBe($noneLabel);
});

it('describes a country scope by its code, and a deleted country as the missing-target label', function (): void {
    $languageId = rgpSeedLanguage();
    $country = rgpSeedCountry($languageId, 'UA');
    $deletedCountry = rgpSeedCountry($languageId, 'GE');
    $deletedId = $deletedCountry->id;
    $deletedCountry->delete();

    $presenter = new RoleGrantPresenter;
    $missingLabel = (string) __('panel.staff.scope.missing_target');

    expect($presenter->scopeDescription('country', $country->id))->toBe('UA')
        ->and($presenter->scopeDescription('country', $deletedId))->toBe($missingLabel);
});

it('describes a territory scope by its translated name, falling back to the missing-target label when untranslated or deleted', function (): void {
    $languageId = rgpSeedLanguage();
    $country = rgpSeedCountry($languageId);
    $translated = rgpSeedTerritory($country->id, 'Chisinau');
    $untranslated = rgpSeedTerritory($country->id, null);
    $deleted = rgpSeedTerritory($country->id, 'Soon Gone');
    $deletedId = $deleted->id;
    $deleted->delete();

    $presenter = new RoleGrantPresenter;
    $missingLabel = (string) __('panel.staff.scope.missing_target');

    expect($presenter->scopeDescription('territory', $translated->id))->toBe('Chisinau')
        ->and($presenter->scopeDescription('territory', $untranslated->id))->toBe($missingLabel)
        ->and($presenter->scopeDescription('territory', $deletedId))->toBe($missingLabel);
});

it('describes a category scope by its translated name, and a deleted object type as the missing-target label', function (): void {
    $type = rgpSeedObjectType('villa', 'Villa');
    $untranslatedType = rgpSeedObjectType('cottage', null);
    $deletedType = rgpSeedObjectType('bungalow', 'Bungalow');
    $deletedId = $deletedType->id;
    $deletedType->delete();

    $presenter = new RoleGrantPresenter;
    $missingLabel = (string) __('panel.staff.scope.missing_target');

    expect($presenter->scopeDescription('category', $type->id))->toBe('Villa')
        ->and($presenter->scopeDescription('category', $untranslatedType->id))->toBe($missingLabel)
        ->and($presenter->scopeDescription('category', $deletedId))->toBe($missingLabel);
});

it('falls back to the missing-target label for a scope kind it does not recognise', function (): void {
    $presenter = new RoleGrantPresenter;

    expect($presenter->scopeDescription('bogus-kind', 42))->toBe((string) __('panel.staff.scope.missing_target'));
});

it('memoizes a resolved scope target name, so a later deletion does not change an already-rendered grant within the same request', function (): void {
    $languageId = rgpSeedLanguage();
    $country = rgpSeedCountry($languageId, 'GE');

    $presenter = new RoleGrantPresenter;

    expect($presenter->scopeDescription('country', $country->id))->toBe('GE');

    $country->delete();

    // Same presenter instance, same scope key — the memo is filled and
    // never re-queried, so the cached code survives the deletion.
    expect($presenter->scopeDescription('country', $country->id))->toBe('GE');
});
