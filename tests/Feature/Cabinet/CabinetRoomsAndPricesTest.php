<?php

declare(strict_types=1);

use App\Filament\Cabinet\Resources\Rooms\Pages\CreateRoom;
use App\Filament\Cabinet\Resources\Rooms\Pages\EditRoom;
use App\Filament\Cabinet\Resources\Rooms\RoomResource;
use App\Models\Object_;
use App\Models\Room;
use App\Models\User;
use Filament\Facades\Filament;
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
| Cabinet Rooms & Prices
|--------------------------------------------------------------------------
|
| The room-category screen exists only for an object whose declared type
| carries the `has_rooms` structural capability — refused at the route
| level for every other type, not merely hidden from the menu. Room content
| (the room's own fields, its translations, and every price attached to it)
| applies immediately regardless of the parent object's publication state:
| a room category is a genuinely relational structure the object-editing
| moderation pipeline has no safe way to replay, so it is deliberately
| exempted from that pipeline the same way an object's own contact channels
| are — proven here directly, under the strictest moderation mode, rather
| than assumed.
|
*/

/** @return array{countryId: int, territoryId: int, typeId: int} */
function cabinetRoomsAndPricesGeography(bool $hasRooms): array
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
    $typeId = DB::table('object_types')->insertGetId([
        'key' => $hasRooms ? 'accommodation' : 'restaurant', 'is_active' => true, 'has_rooms' => $hasRooms,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return compact('countryId', 'territoryId', 'typeId');
}

function cabinetRoomsAndPricesOwner(string $roleKey): User
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

/**
 * @param  array{countryId: int, territoryId: int, typeId: int}  $fixture
 * @param  array<string, mixed>  $overrides
 */
function cabinetRoomsAndPricesMakeObject(array $fixture, int $ownerId, string $status, array $overrides = []): Object_
{
    $objectId = DB::table('objects')->insertGetId(array_merge([
        'ulid' => (string) Str::ulid(),
        'owner_id' => $ownerId,
        'object_type_id' => $fixture['typeId'],
        'territory_id' => $fixture['territoryId'],
        'country_id' => $fixture['countryId'],
        'status' => $status,
        'moderation_status' => $status === 'published' ? 'approved' : null,
        'latitude' => 45.1234500,
        'longitude' => 28.1234500,
        'created_at' => now(), 'updated_at' => now(),
    ], $overrides));

    DB::table('object_translations')->insert([
        'object_id' => $objectId, 'locale' => 'en', 'name' => 'Riverside Guesthouse',
        'slug' => 'riverside-guesthouse-'.$objectId, 'created_at' => now(), 'updated_at' => now(),
    ]);

    /** @var Object_ $object */
    $object = Object_::query()->withUnmoderated()->findOrFail($objectId);

    return $object;
}

function cabinetRoomsAndPricesSetMode(int $objectId, string $mode): void
{
    DB::table('moderation_settings')->insert([
        'scope_level' => 'object', 'scope_reference_id' => $objectId, 'mode' => $mode,
        'set_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function cabinetRoomsAndPricesMountTenant(Object_ $object): void
{
    Filament::setCurrentPanel(Filament::getPanel('cabinet'));
    // A real HTTP request boots the current panel as part of its own
    // middleware stack, which is what registers the tenant
    // auto-association listener `RoomResource`'s create flow relies on
    // (`BelongsToTenant::observeTenancyModelCreation()`). A `Livewire::test()`
    // call never sends a request through that stack, so it has to be
    // booted explicitly here instead.
    Filament::bootCurrentPanel();
    Filament::setTenant($object, isQuiet: true);
}

it('refuses every one of its own routes for an object whose type does not declare has_rooms, not merely hiding the navigation entry', function (): void {
    $fixture = cabinetRoomsAndPricesGeography(hasRooms: false);
    $owner = cabinetRoomsAndPricesOwner('rooms_owner_refused');
    $object = cabinetRoomsAndPricesMakeObject($fixture, $owner->id, 'published');

    // `canAccess()`/`shouldRegisterNavigation()` resolve through the bound
    // Policy, which needs a real authenticated actor to evaluate against —
    // set here, before either static check, rather than only on the HTTP
    // calls below.
    $this->actingAs($owner);
    cabinetRoomsAndPricesMountTenant($object);

    expect(RoomResource::shouldRegisterNavigation())->toBeFalse()
        ->and(RoomResource::canAccess())->toBeFalse();

    $this->get(RoomResource::getUrl('index', [], panel: 'cabinet', tenant: $object))
        ->assertForbidden();

    $this->get(RoomResource::getUrl('create', [], panel: 'cabinet', tenant: $object))
        ->assertForbidden();
});

it('is reachable for an accommodation-type object whose type declares has_rooms', function (): void {
    $fixture = cabinetRoomsAndPricesGeography(hasRooms: true);
    $owner = cabinetRoomsAndPricesOwner('rooms_owner_reachable');
    $object = cabinetRoomsAndPricesMakeObject($fixture, $owner->id, 'published');

    $this->actingAs($owner);
    cabinetRoomsAndPricesMountTenant($object);

    expect(RoomResource::shouldRegisterNavigation())->toBeTrue()
        ->and(RoomResource::canAccess())->toBeTrue();

    $this->get(RoomResource::getUrl('index', [], panel: 'cabinet', tenant: $object))
        ->assertSuccessful();
});

it('lets an owner create multiple room categories, each with its full field set', function (): void {
    $fixture = cabinetRoomsAndPricesGeography(hasRooms: true);
    $owner = cabinetRoomsAndPricesOwner('rooms_owner_multiple_categories');
    $object = cabinetRoomsAndPricesMakeObject($fixture, $owner->id, 'draft');

    cabinetRoomsAndPricesMountTenant($object);

    Livewire::actingAs($owner)
        ->test(CreateRoom::class)
        ->fillForm([
            'capacity' => 2,
            'room_count' => 5,
            'area_sqm' => 24.5,
            'bed_configuration' => '1 double bed',
            'max_guests' => 3,
            'has_extra_bed' => true,
            'display_order' => 1,
            'is_active' => true,
            'translations' => ['en' => ['name' => 'Deluxe Double', 'description' => 'A spacious double room.']],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    Livewire::actingAs($owner)
        ->test(CreateRoom::class)
        ->fillForm([
            'capacity' => 4,
            'room_count' => 2,
            'area_sqm' => 40.0,
            'bed_configuration' => '2 double beds',
            'max_guests' => 5,
            'has_extra_bed' => false,
            'display_order' => 2,
            'is_active' => true,
            'translations' => ['en' => ['name' => 'Family Suite', 'description' => 'A suite for the whole family.']],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $rooms = DB::table('rooms')->where('object_id', $object->id)->orderBy('display_order')->get();

    expect($rooms)->toHaveCount(2);

    $deluxe = $rooms[0];
    expect($deluxe->capacity)->toBe(2)
        ->and($deluxe->room_count)->toBe(5)
        ->and((float) $deluxe->area_sqm)->toBe(24.5)
        ->and($deluxe->bed_configuration)->toBe('1 double bed')
        ->and($deluxe->max_guests)->toBe(3)
        ->and((bool) $deluxe->has_extra_bed)->toBeTrue()
        ->and((bool) $deluxe->is_active)->toBeTrue();

    $family = $rooms[1];
    expect($family->capacity)->toBe(4)
        ->and($family->room_count)->toBe(2)
        ->and((bool) $family->has_extra_bed)->toBeFalse();

    $deluxeTranslation = DB::table('room_translations')->where('room_id', $deluxe->id)->where('locale', 'en')->first();
    $familyTranslation = DB::table('room_translations')->where('room_id', $family->id)->where('locale', 'en')->first();

    expect($deluxeTranslation->name)->toBe('Deluxe Double')
        ->and($deluxeTranslation->description)->toBe('A spacious double room.')
        ->and($familyTranslation->name)->toBe('Family Suite');

    // Every row genuinely belongs to this tenant object — proves the
    // automatic tenant association Filament's own cabinet tenancy performs
    // through `Room::object()`, not a hand-rolled foreign key assignment.
    expect(DB::table('rooms')->where('object_id', $object->id)->count())->toBe(2);
});

it("round-trips a price's calculation unit and its optional validity period", function (): void {
    $fixture = cabinetRoomsAndPricesGeography(hasRooms: true);
    $owner = cabinetRoomsAndPricesOwner('rooms_owner_price_roundtrip');
    $object = cabinetRoomsAndPricesMakeObject($fixture, $owner->id, 'draft');

    cabinetRoomsAndPricesMountTenant($object);

    Livewire::actingAs($owner)
        ->test(CreateRoom::class)
        ->fillForm([
            'translations' => ['en' => ['name' => 'Seasonal Suite']],
            'prices' => [
                [
                    'type' => 'High season',
                    'calculation_unit' => 'per_night',
                    'amount' => 120.50,
                    'currency' => 'eur',
                    'valid_from' => '2026-06-01',
                    'valid_until' => '2026-09-30',
                    'comment' => 'Summer rate.',
                ],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $roomId = DB::table('rooms')->where('object_id', $object->id)->value('id');
    $price = DB::table('prices')->where('priceable_type', Room::class)->where('priceable_id', $roomId)->first();

    expect($price)->not->toBeNull()
        ->and($price->type)->toBe('High season')
        ->and($price->calculation_unit)->toBe('per_night')
        ->and((float) $price->amount)->toBe(120.50)
        // Uppercased on save, regardless of how the owner typed it in.
        ->and($price->currency)->toBe('EUR')
        ->and((string) $price->valid_from)->toBe('2026-06-01')
        ->and((string) $price->valid_until)->toBe('2026-09-30')
        ->and($price->comment)->toBe('Summer rate.');
});

it('applies a new room and its price immediately on an already-published object, even under the strictest moderation mode', function (): void {
    $fixture = cabinetRoomsAndPricesGeography(hasRooms: true);
    $owner = cabinetRoomsAndPricesOwner('rooms_owner_immediate_create');
    $object = cabinetRoomsAndPricesMakeObject($fixture, $owner->id, 'published');
    // 'review' is the mode that would withhold an *object* field edit behind
    // a pending request — proving room/price content is never routed
    // through that same pipeline, not merely that it happens to publish
    // immediately under the portal's default mode.
    cabinetRoomsAndPricesSetMode($object->id, 'review');

    cabinetRoomsAndPricesMountTenant($object);

    Livewire::actingAs($owner)
        ->test(CreateRoom::class)
        ->fillForm([
            'capacity' => 2,
            'translations' => ['en' => ['name' => 'Garden Room']],
            'prices' => [
                ['calculation_unit' => 'per_night', 'amount' => 80, 'currency' => 'EUR'],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $room = DB::table('rooms')->where('object_id', $object->id)->first();

    expect($room)->not->toBeNull()
        ->and($room->capacity)->toBe(2);

    expect(DB::table('room_translations')->where('room_id', $room->id)->where('locale', 'en')->value('name'))
        ->toBe('Garden Room');

    expect(DB::table('prices')->where('priceable_type', Room::class)->where('priceable_id', $room->id)->count())
        ->toBe(1);

    // No moderation request was created for this object as a side effect of
    // the room/price write — the falsifying proof that the exemption holds
    // even in the one mode designed to withhold everything else.
    expect(DB::table('moderation_requests')->where('target_id', $object->id)->where('target_type', Object_::class)->count())
        ->toBe(0);
});

it('applies a room edit and a further price immediately on an already-published object under review mode', function (): void {
    $fixture = cabinetRoomsAndPricesGeography(hasRooms: true);
    $owner = cabinetRoomsAndPricesOwner('rooms_owner_immediate_update');
    $object = cabinetRoomsAndPricesMakeObject($fixture, $owner->id, 'published');

    cabinetRoomsAndPricesMountTenant($object);

    // Created first under the portal's default mode, then the object is
    // switched to 'review' before the edit below — isolating the assertion
    // to the update path specifically.
    Livewire::actingAs($owner)
        ->test(CreateRoom::class)
        ->fillForm(['capacity' => 2, 'translations' => ['en' => ['name' => 'Original Name']]])
        ->call('create')
        ->assertHasNoFormErrors();

    $roomId = DB::table('rooms')->where('object_id', $object->id)->value('id');
    cabinetRoomsAndPricesSetMode($object->id, 'review');

    Livewire::actingAs($owner)
        ->test(EditRoom::class, ['record' => $roomId])
        ->fillForm([
            'capacity' => 6,
            'translations' => ['en' => ['name' => 'Renamed Room']],
            'prices' => [
                ['calculation_unit' => 'per_room', 'amount' => 200, 'currency' => 'EUR'],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(DB::table('rooms')->where('id', $roomId)->value('capacity'))->toBe(6)
        ->and(DB::table('room_translations')->where('room_id', $roomId)->where('locale', 'en')->value('name'))
        ->toBe('Renamed Room')
        ->and(DB::table('prices')->where('priceable_type', Room::class)->where('priceable_id', $roomId)->count())
        ->toBe(1);

    expect(DB::table('moderation_requests')->where('target_id', $object->id)->where('target_type', Object_::class)->count())
        ->toBe(0);
});
