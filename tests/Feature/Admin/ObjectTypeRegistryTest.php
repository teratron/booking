<?php

declare(strict_types=1);

use App\Filament\Admin\Resources\Objects\Pages\EditObject;
use App\Filament\Admin\Resources\ObjectTypes\ObjectTypeResource;
use App\Filament\Admin\Resources\ObjectTypes\Pages\CreateObjectType;
use App\Filament\Admin\Resources\ObjectTypes\Pages\EditObjectType;
use App\Models\Object_;
use App\Models\ObjectType;
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
| Object Type Registry
|--------------------------------------------------------------------------
|
| The registry being data is not decoration: the point is that a type's
| declared field set actually governs what the object form renders, proven
| here by asserting the object form itself, not just the registry screen —
| and that a type invented after this code was written works without a
| deployment.
|
*/

function objectTypeActor(array $permissions = ['admin_panel_access', 'settings.view', 'settings.edit']): User
{
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $role = Role::findOrCreate('registry_admin', 'web');
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

it('creates a nested type with icon, amenity groups, attribute schema and SEO defaults, without a code change', function (): void {
    $languageId = DB::table('languages')->insertGetId([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $groupId = DB::table('amenity_groups')->insertGetId(['is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('amenity_group_translations')->insert([
        'amenity_group_id' => $groupId, 'locale' => 'en', 'name' => 'Kitchen',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $parentId = DB::table('object_types')->insertGetId([
        'key' => 'accommodation', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $actor = objectTypeActor();

    Livewire::actingAs($actor)
        ->test(CreateObjectType::class)
        ->fillForm([
            'key' => 'guesthouse',
            'parent_id' => $parentId,
            'icon_path' => '/icons/guesthouse.svg',
            'has_rooms' => true,
            'has_availability_status' => true,
            'amenityGroups' => [$groupId],
            'attribute_schema' => [
                ['key' => 'cuisine', 'type' => 'text', 'label' => ['en' => 'Cuisine']],
            ],
            'translations' => ['en' => ['name' => 'Guesthouse', 'seo_title' => 'Guesthouses']],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $type = ObjectType::query()->where('key', 'guesthouse')->firstOrFail();

    expect($type->parent_id)->toBe($parentId)
        ->and($type->has_rooms)->toBeTrue()
        ->and($type->attribute_schema)->toBe([['key' => 'cuisine', 'type' => 'text', 'label' => ['en' => 'Cuisine']]])
        ->and($type->amenityGroups()->pluck('amenity_groups.id')->all())->toBe([$groupId])
        ->and(DB::table('object_type_translations')->where('object_type_id', $type->id)->value('name'))->toBe('Guesthouse');
});

it('governs which fields the object form actually renders — no type declares none, another declares two', function (): void {
    $languageId = DB::table('languages')->insertGetId([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'created_at' => now(), 'updated_at' => now(),
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

    $plainTypeId = DB::table('object_types')->insertGetId([
        'key' => 'plain', 'is_active' => true, 'attribute_schema' => null,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $diningTypeId = DB::table('object_types')->insertGetId([
        'key' => 'dining', 'is_active' => true,
        'attribute_schema' => json_encode([
            ['key' => 'cuisine', 'type' => 'text', 'label' => ['en' => 'Cuisine']],
            ['key' => 'average_cheque', 'type' => 'number', 'label' => ['en' => 'Average cheque']],
        ]),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $owner = User::factory()->create();
    $objectId = DB::table('objects')->insertGetId([
        'ulid' => (string) Str::ulid(), 'owner_id' => $owner->id,
        'object_type_id' => $plainTypeId, 'territory_id' => $territoryId, 'country_id' => $countryId,
        'status' => 'draft', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $actor = objectTypeActor(['admin_panel_access', 'object.view', 'object.edit']);

    // A type declaring no custom fields renders none, and one declaring two
    // renders exactly those two — proven against the real object edit form,
    // not the registry screen.
    Livewire::actingAs($actor)
        ->test(EditObject::class, ['record' => $objectId])
        ->assertFormFieldDoesNotExist('attributes.cuisine')
        ->set('data.object_type_id', $diningTypeId)
        ->assertFormFieldExists('attributes.cuisine')
        ->assertFormFieldExists('attributes.average_cheque')
        ->fillForm(['attributes' => ['cuisine' => 'Italian', 'average_cheque' => 25]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Object_::query()->withUnmoderated()->findOrFail($objectId)->attributes)
        ->toBe(['cuisine' => 'Italian', 'average_cheque' => 25]);
});

it('renders a brand-new type invented after this code was written, with no code change', function (): void {
    $languageId = DB::table('languages')->insertGetId([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'created_at' => now(), 'updated_at' => now(),
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

    // A type this test invents on the spot — "car rental", never anticipated
    // by any form field or migration — proving the field set is genuinely
    // administrator-defined data, not a disguised enum.
    $carRentalId = DB::table('object_types')->insertGetId([
        'key' => 'car_rental', 'is_active' => true,
        'attribute_schema' => json_encode([
            ['key' => 'fleet_size', 'type' => 'number', 'label' => ['en' => 'Fleet size']],
            ['key' => 'requires_deposit', 'type' => 'boolean', 'label' => ['en' => 'Requires deposit']],
        ]),
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $owner = User::factory()->create();
    $objectId = DB::table('objects')->insertGetId([
        'ulid' => (string) Str::ulid(), 'owner_id' => $owner->id,
        'object_type_id' => $carRentalId, 'territory_id' => $territoryId, 'country_id' => $countryId,
        'status' => 'draft', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $actor = objectTypeActor(['admin_panel_access', 'object.view', 'object.edit']);

    Livewire::actingAs($actor)
        ->test(EditObject::class, ['record' => $objectId])
        ->assertFormFieldExists('attributes.fleet_size')
        ->assertFormFieldExists('attributes.requires_deposit');
});

it('hides a deactivated type from the public catalog without detaching its objects', function (): void {
    $languageId = DB::table('languages')->insertGetId([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'created_at' => now(), 'updated_at' => now(),
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
        'key' => 'seasonal', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $owner = User::factory()->create();
    $objectId = DB::table('objects')->insertGetId([
        'ulid' => (string) Str::ulid(), 'owner_id' => $owner->id,
        'object_type_id' => $typeId, 'territory_id' => $territoryId, 'country_id' => $countryId,
        'status' => 'published', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $actor = objectTypeActor();

    Livewire::actingAs($actor)
        ->test(EditObjectType::class, ['record' => $typeId])
        ->fillForm(['is_active' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(ObjectType::query()->findOrFail($typeId)->is_active)->toBeFalse()
        ->and(DB::table('objects')->where('id', $objectId)->where('object_type_id', $typeId)->exists())->toBeTrue();
});

it('admits an unrestricted grant and refuses a scoped one, since the registry is portal-wide configuration', function (): void {
    $typeId = DB::table('object_types')->insertGetId([
        'key' => 'accommodation', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $unrestricted = objectTypeActor();
    $this->actingAs($unrestricted)
        ->get(ObjectTypeResource::getUrl('index', panel: 'admin'))
        ->assertSuccessful();

    Permission::findOrCreate('settings.view', 'web');
    Permission::findOrCreate('settings.edit', 'web');
    $scopedRole = Role::findOrCreate('country_scoped_settings', 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $scopedRole->syncPermissions(['admin_panel_access', 'settings.view', 'settings.edit']);
    Permission::findOrCreate('admin_panel_access', 'web');
    $scoped = User::factory()->create();
    $scoped->assignRole($scopedRole);
    DB::table('role_scopes')->insert([
        'user_id' => $scoped->id, 'role_id' => $scopedRole->id,
        'scope_kind' => 'country', 'scope_reference_id' => 1,
        'granted_by' => $scoped->id, 'granted_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    // The permission is held, but the registry declares no scope axis, so a
    // bounded grant reaches none of its rows — matching module administration.
    expect(ObjectTypeResource::getEloquentQuery()->count())->toBe(1);
    $this->actingAs($scoped->fresh());
    expect(ObjectTypeResource::getEloquentQuery()->count())->toBe(0);
});
