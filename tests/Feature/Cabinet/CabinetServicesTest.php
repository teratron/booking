<?php

declare(strict_types=1);

use App\Filament\Cabinet\Resources\Services\Pages\EditService;
use App\Models\Object_;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Field;
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
| Cabinet Services (Amenities) Selection
|--------------------------------------------------------------------------
|
| An owner never invents a service — every selectable value on this screen
| traces back to a real row an administrator maintains in the amenity
| registry, and only the slice of that registry the object's own declared
| type is allowed to offer. Proven three ways below: the form's own field
| list carries no free-text component at all (a structural guarantee, not a
| visual one); two object types with disjoint applicable groups are offered
| disjoint sets; and a selection round-trips correctly through the object's
| own `amenities` relation, including leaving an untouched group's own
| selection alone.
|
*/

/** @return array{countryId: int, territoryId: int} */
function cabinetServicesGeography(): array
{
    $languageId = DB::table('languages')->insertGetId([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true, 'display_order' => 1,
        'created_at' => now(), 'updated_at' => now(),
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

    return compact('countryId', 'territoryId');
}

function cabinetServicesMakeType(string $key): int
{
    return DB::table('object_types')->insertGetId([
        'key' => $key, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

/**
 * Creates one amenity group with the given amenities (each `[key, isActive]`)
 * and links it to every object type ID passed in `$applicableTypeIds` —
 * the pivot the owner cabinet's own catalog service filters against.
 *
 * @param  list<array{0: string, 1: bool}>  $amenities
 * @param  list<int>  $applicableTypeIds
 * @return array{groupId: int, amenityIds: array<string, int>}
 */
function cabinetServicesMakeGroup(string $key, array $amenities, array $applicableTypeIds): array
{
    $groupId = DB::table('amenity_groups')->insertGetId([
        'is_active' => true, 'display_order' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);

    DB::table('amenity_group_translations')->insert([
        'amenity_group_id' => $groupId, 'locale' => 'en', 'name' => ucfirst(str_replace('_', ' ', $key)),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    foreach ($applicableTypeIds as $typeId) {
        DB::table('amenity_group_object_type')->insert([
            'amenity_group_id' => $groupId, 'object_type_id' => $typeId,
        ]);
    }

    $amenityIds = [];

    foreach ($amenities as $order => [$amenityKey, $isActive]) {
        $amenityId = DB::table('amenities')->insertGetId([
            'amenity_group_id' => $groupId, 'is_filterable' => true, 'is_active' => $isActive,
            'display_order' => $order + 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('amenity_translations')->insert([
            'amenity_id' => $amenityId, 'locale' => 'en', 'name' => ucfirst(str_replace('_', ' ', $amenityKey)),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $amenityIds[$amenityKey] = $amenityId;
    }

    return ['groupId' => $groupId, 'amenityIds' => $amenityIds];
}

function cabinetServicesOwner(string $roleKey): User
{
    foreach (['object.view', 'object.edit', 'cabinet_access'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $role = Role::findOrCreate($roleKey, 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $role->syncPermissions(['object.view', 'object.edit', 'cabinet_access']);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user->fresh();
}

/** @param  array{countryId: int, territoryId: int}  $geography */
function cabinetServicesMakeObject(array $geography, int $typeId, int $ownerId): Object_
{
    $objectId = DB::table('objects')->insertGetId([
        'ulid' => (string) Str::ulid(),
        'owner_id' => $ownerId,
        'object_type_id' => $typeId,
        'territory_id' => $geography['territoryId'],
        'country_id' => $geography['countryId'],
        'status' => 'draft',
        'latitude' => 45.1234500,
        'longitude' => 28.1234500,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    DB::table('object_translations')->insert([
        'object_id' => $objectId, 'locale' => 'en', 'name' => 'Test Object',
        'slug' => 'test-object-'.$objectId, 'created_at' => now(), 'updated_at' => now(),
    ]);

    // Every scope stripped, not only `ModerationScope`: once an earlier
    // object in this same test has already been mounted as the current
    // tenant, Filament's own tenancy scope is registered directly on
    // `Object_::class` for the rest of the process (see
    // `BelongsToTenant::registerTenancyModelGlobalScope()`) and would narrow
    // this plain fixture lookup down to that *other* object's own row.
    /** @var Object_ $object */
    $object = Object_::query()->withoutGlobalScopes()->findOrFail($objectId);

    return $object;
}

function cabinetServicesMountTenant(Object_ $object): void
{
    Filament::setCurrentPanel(Filament::getPanel('cabinet'));
    Filament::bootCurrentPanel();
    Filament::setTenant($object, isQuiet: true);
}

it('never exposes a free-text field — every field in the form is a checkbox list bound to the amenity registry', function (): void {
    $geography = cabinetServicesGeography();
    $typeId = cabinetServicesMakeType('hotel');
    $general = cabinetServicesMakeGroup('general', [['wifi', true], ['parking', true], ['retired_amenity', false]], [$typeId]);

    $owner = cabinetServicesOwner('services_owner_no_free_text');
    $object = cabinetServicesMakeObject($geography, $typeId, $owner->id);
    cabinetServicesMountTenant($object);

    $page = Livewire::actingAs($owner)
        ->test(EditService::class, ['record' => $object->getKey()])
        ->instance();

    $fields = $page->getSchema('form')->getFlatFields();

    expect($fields)->not->toBeEmpty();

    foreach ($fields as $field) {
        expect($field)->toBeInstanceOf(Field::class)
            ->and($field)->toBeInstanceOf(CheckboxList::class);
    }

    // The one field on this form exposes exactly the group's own active
    // amenities as options — never an arbitrary or freely-typed value, and
    // never the amenity an administrator has deactivated.
    /** @var CheckboxList $field */
    $field = reset($fields);
    $options = $field->getOptions();

    expect($options)->toHaveCount(2)
        ->and($options)->toHaveKey((string) $general['amenityIds']['wifi'])
        ->and($options)->toHaveKey((string) $general['amenityIds']['parking'])
        ->and($options)->not->toHaveKey((string) $general['amenityIds']['retired_amenity']);
});

it("offers only the amenity groups applicable to the object's own declared type, not the full registry", function (): void {
    $geography = cabinetServicesGeography();
    $hotelType = cabinetServicesMakeType('hotel');
    $restaurantType = cabinetServicesMakeType('restaurant');

    cabinetServicesMakeGroup('rooms', [['air_conditioning', true]], [$hotelType]);
    cabinetServicesMakeGroup('catering', [['buffet', true]], [$restaurantType]);

    $owner = cabinetServicesOwner('services_owner_type_scoped');

    $hotel = cabinetServicesMakeObject($geography, $hotelType, $owner->id);
    cabinetServicesMountTenant($hotel);

    Livewire::actingAs($owner)
        ->test(EditService::class, ['record' => $hotel->getKey()])
        ->assertSee('Rooms')
        ->assertSee('Air conditioning')
        ->assertDontSee('Catering')
        ->assertDontSee('Buffet');

    $restaurant = cabinetServicesMakeObject($geography, $restaurantType, $owner->id);
    cabinetServicesMountTenant($restaurant);

    Livewire::actingAs($owner)
        ->test(EditService::class, ['record' => $restaurant->getKey()])
        ->assertSee('Catering')
        ->assertSee('Buffet')
        ->assertDontSee('Rooms')
        ->assertDontSee('Air conditioning');
});

it("round-trips a selection through the object's own amenities relation, leaving another group's selection untouched", function (): void {
    $geography = cabinetServicesGeography();
    $typeId = cabinetServicesMakeType('hotel');

    $general = cabinetServicesMakeGroup('general', [['wifi', true], ['parking', true]], [$typeId]);
    $rooms = cabinetServicesMakeGroup('rooms', [['air_conditioning', true], ['private_bathroom', true]], [$typeId]);

    $owner = cabinetServicesOwner('services_owner_round_trip');
    $object = cabinetServicesMakeObject($geography, $typeId, $owner->id);
    cabinetServicesMountTenant($object);

    Livewire::actingAs($owner)
        ->test(EditService::class, ['record' => $object->getKey()])
        ->fillForm([
            "amenities_group_{$general['groupId']}" => [$general['amenityIds']['wifi']],
            "amenities_group_{$rooms['groupId']}" => [$rooms['amenityIds']['air_conditioning']],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $selected = DB::table('amenity_object')->where('object_id', $object->id)->pluck('amenity_id')->all();

    expect($selected)->toEqualCanonicalizing([
        $general['amenityIds']['wifi'],
        $rooms['amenityIds']['air_conditioning'],
    ]);

    // Editing the "general" group's own selection again — swapping wifi for
    // parking — must leave the "rooms" group's own already-saved amenity
    // completely alone, proving each group's checkbox list only ever
    // touches its own pivot rows.
    Livewire::actingAs($owner)
        ->test(EditService::class, ['record' => $object->getKey()])
        ->fillForm([
            "amenities_group_{$general['groupId']}" => [$general['amenityIds']['parking']],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $selectedAfter = DB::table('amenity_object')->where('object_id', $object->id)->pluck('amenity_id')->all();

    expect($selectedAfter)->toEqualCanonicalizing([
        $general['amenityIds']['parking'],
        $rooms['amenityIds']['air_conditioning'],
    ]);
});
