<?php

declare(strict_types=1);

use App\Filament\Admin\Resources\Objects\Pages\CreateObject;
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
| Create Object — Creation-Specific Behaviour
|--------------------------------------------------------------------------
|
| ObjectResourceFormTest already proves the shared form schema and the
| EditObject lifecycle. What is specific to creation instead: the defaults
| mutateFormDataBeforeCreate stamps onto a brand-new record, the
| translations reconciliation handleRecordCreation performs against a
| record that did not exist a moment ago, that an owner must be picked
| explicitly rather than defaulting to the creating administrator, and
| every branch of the pre-record scope check a policy alone cannot express
| because there is no record yet to check it against.
|
*/

function createObjectFixture(bool $withSecondLanguage = false): array
{
    $languageId = DB::table('languages')->insertGetId([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    if ($withSecondLanguage) {
        DB::table('languages')->insert([
            'code' => 'ru', 'short_label' => 'RU', 'is_active' => true, 'is_primary' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    $countryMd = DB::table('countries')->insertGetId([
        'code' => 'MD', 'currency' => 'MDL', 'phone_code' => '+373',
        'primary_language_id' => $languageId, 'is_active' => true, 'display_order' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $countryUa = DB::table('countries')->insertGetId([
        'code' => 'UA', 'currency' => 'UAH', 'phone_code' => '+380',
        'primary_language_id' => $languageId, 'is_active' => true, 'display_order' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $levelMd = DB::table('territory_levels')->insertGetId([
        'country_id' => $countryMd, 'depth_rank' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $territoryMd = DB::table('territories')->insertGetId([
        'country_id' => $countryMd, 'level_id' => $levelMd, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $typeStay = DB::table('object_types')->insertGetId([
        'key' => 'accommodation', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $typeDining = DB::table('object_types')->insertGetId([
        'key' => 'dining', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $owner = User::factory()->create();

    return [
        'owner' => $owner,
        'countryMd' => $countryMd,
        'countryUa' => $countryUa,
        'territoryMd' => $territoryMd,
        'typeStay' => $typeStay,
        'typeDining' => $typeDining,
    ];
}

/**
 * $scopeKind of null leaves the role with the permission but no matching
 * `role_scopes` row at all — the "grant exists, but no grant reaches
 * anything" shape distinct from an explicit unrestricted ('none') grant.
 */
function createObjectActor(array $permissions, ?string $scopeKind, ?int $reference, string $roleKey): User
{
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $role = Role::findOrCreate($roleKey, 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $role->syncPermissions($permissions);

    $user = User::factory()->create();
    $user->assignRole($role);

    if ($scopeKind !== null) {
        DB::table('role_scopes')->insert([
            'user_id' => $user->id, 'role_id' => $role->id,
            'scope_kind' => $scopeKind, 'scope_reference_id' => $reference,
            'granted_by' => $user->id, 'granted_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    return $user->fresh();
}

function createObjectPermissions(): array
{
    return ['admin_panel_access', 'object.view', 'object.create'];
}

it('stamps a generated ulid and the draft default onto a new object, and reconciles only the translation actually filled in', function (): void {
    $fixture = createObjectFixture(withSecondLanguage: true);
    $actor = createObjectActor(createObjectPermissions(), 'none', null, 'unrestricted_creator');

    Livewire::actingAs($actor)
        ->test(CreateObject::class)
        ->fillForm([
            'object_type_id' => $fixture['typeStay'],
            'country_id' => $fixture['countryMd'],
            'territory_id' => $fixture['territoryMd'],
            'owner_id' => $fixture['owner']->id,
            'translations' => ['en' => ['name' => 'Seaside Villa']],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $record = DB::table('objects')->where('owner_id', $fixture['owner']->id)->first();

    expect($record)->not->toBeNull()
        ->and(Str::isUlid($record->ulid))->toBeTrue()
        ->and($record->status)->toBe('draft');

    // Only 'en' was filled in; the 'ru' section was left entirely blank, so
    // array_filter() in handleRecordCreation() must reduce it to an empty
    // array and skip it — inserting a row would otherwise violate the
    // NOT NULL constraint on object_translations.name/.slug.
    $translations = DB::table('object_translations')->where('object_id', $record->id)->get();

    expect($translations)->toHaveCount(1);

    $translation = $translations->first();

    expect($translation->locale)->toBe('en')
        ->and($translation->name)->toBe('Seaside Villa')
        ->and($translation->slug)->toBe(Str::slug('Seaside Villa-'.$record->id));
});

it('requires an owner to be explicitly picked — creation never defaults ownership to the acting administrator', function (): void {
    $fixture = createObjectFixture();
    $actor = createObjectActor(createObjectPermissions(), 'none', null, 'unrestricted_creator');

    Livewire::actingAs($actor)
        ->test(CreateObject::class)
        ->fillForm([
            'object_type_id' => $fixture['typeStay'],
            'country_id' => $fixture['countryMd'],
            'territory_id' => $fixture['territoryMd'],
            'translations' => ['en' => ['name' => 'No Owner Yet']],
        ])
        ->call('create')
        ->assertHasFormErrors(['owner_id']);

    expect(DB::table('objects')->count())->toBe(0);
});

it('refuses a category-scoped administrator creating an object of another category', function (): void {
    $fixture = createObjectFixture();
    $actor = createObjectActor(
        ['admin_panel_access', 'object.view', 'object.create'],
        'category',
        $fixture['typeDining'],
        'dining_only_creator',
    );

    Livewire::actingAs($actor)
        ->test(CreateObject::class)
        ->fillForm([
            'object_type_id' => $fixture['typeStay'],
            'country_id' => $fixture['countryMd'],
            'territory_id' => $fixture['territoryMd'],
            'owner_id' => $fixture['owner']->id,
        ])
        ->call('create')
        ->assertHasFormErrors(['object_type_id']);

    expect(DB::table('objects')->count())->toBe(0);
});

it('refuses creating anything at all for an administrator whose role grants object.create but carries no scope row', function (): void {
    // Distinct from the unrestricted ('none') case above: the role holds the
    // permission, but role_scopes has no matching row for it at all, so
    // constraintFor() resolves every axis to an empty list — reachesNothing()
    // — rather than the isUnrestricted branch. A deny-everything grant, not
    // an unrestricted one.
    $fixture = createObjectFixture();
    $actor = createObjectActor(createObjectPermissions(), null, null, 'ungranted_creator');

    Livewire::actingAs($actor)
        ->test(CreateObject::class)
        ->fillForm([
            'object_type_id' => $fixture['typeStay'],
            'country_id' => $fixture['countryMd'],
            'territory_id' => $fixture['territoryMd'],
            'owner_id' => $fixture['owner']->id,
        ])
        ->call('create')
        ->assertHasFormErrors(['country_id']);

    expect(DB::table('objects')->count())->toBe(0);
});
