<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Structured Data & Breadcrumbs, Gated on Module State (T-6B03)
|--------------------------------------------------------------------------
|
| Each page type emits the Schema.org entity the specification names for
| it, and every page below home carries a BreadcrumbList. An object page
| never emits offer availability while the booking module is inactive for
| it — a durable trust penalty, not a cosmetic error, if it did.
|
*/

/** @return array{languageId: int, countryId: int} */
function structuredDataRegistry(): array
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

function structuredDataMakeTerritory(int $countryId, string $name): int
{
    $levelId = DB::table('territory_levels')->insertGetId([
        'country_id' => $countryId, 'depth_rank' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $territoryId = DB::table('territories')->insertGetId([
        'country_id' => $countryId, 'level_id' => $levelId, 'is_active' => true,
        'latitude' => 47.0, 'longitude' => 28.8,
        'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $slug = Str::slug($name);

    DB::table('territory_translations')->insert([
        'territory_id' => $territoryId, 'country_id' => $countryId, 'locale' => 'en', 'name' => $name,
        'slug' => $slug, 'full_slug_path' => $slug, 'needs_review' => false, 'published_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return $territoryId;
}

function structuredDataMakeType(string $key, string $kind, bool $hasRooms = false): int
{
    $typeId = DB::table('object_types')->insertGetId([
        'key' => $key, 'is_active' => true, 'has_rooms' => $hasRooms, 'has_availability_status' => $hasRooms,
        'structured_data_kind' => $kind, 'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('object_type_translations')->insert([
        'object_type_id' => $typeId, 'locale' => 'en', 'name' => ucfirst($key), 'slug' => $key,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return $typeId;
}

/** @return array{objectId: int, slug: string} */
function structuredDataMakeObject(int $countryId, int $territoryId, int $typeId, string $name): array
{
    $ownerId = DB::table('users')->insertGetId([
        'name' => 'Owner', 'email' => Str::random(10).'@example.test', 'password' => bcrypt('secret'),
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $objectId = DB::table('objects')->insertGetId([
        'ulid' => (string) Str::ulid(), 'owner_id' => $ownerId,
        'object_type_id' => $typeId, 'territory_id' => $territoryId, 'country_id' => $countryId,
        'address' => 'Test Street 1', 'latitude' => 47.01, 'longitude' => 28.81,
        'status' => 'published', 'moderation_status' => 'approved', 'availability_status' => 'available',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $slug = Str::slug($name).'-'.$objectId;

    DB::table('object_translations')->insert([
        'object_id' => $objectId, 'locale' => 'en', 'name' => $name,
        'slug' => $slug, 'needs_review' => false, 'published_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return ['objectId' => $objectId, 'slug' => $slug];
}

it('emits a LodgingBusiness entity for an accommodation object', function (): void {
    $fixture = structuredDataRegistry();
    $territoryId = structuredDataMakeTerritory($fixture['countryId'], 'Lodging Town');
    $typeId = structuredDataMakeType('hotel', 'lodging', hasRooms: true);
    $object = structuredDataMakeObject($fixture['countryId'], $territoryId, $typeId, 'Lodging Hotel');

    $this->get("/en/o/{$object['slug']}")
        ->assertOk()
        ->assertSee('"@type":"LodgingBusiness"', false);
});

it('emits a FoodEstablishment entity for a dining object', function (): void {
    $fixture = structuredDataRegistry();
    $territoryId = structuredDataMakeTerritory($fixture['countryId'], 'Dining Town');
    $typeId = structuredDataMakeType('restaurant', 'food');
    $object = structuredDataMakeObject($fixture['countryId'], $territoryId, $typeId, 'Dining Restaurant');

    $this->get("/en/o/{$object['slug']}")
        ->assertOk()
        ->assertSee('"@type":"FoodEstablishment"', false);
});

it('emits a Place entity for an attraction object', function (): void {
    $fixture = structuredDataRegistry();
    $territoryId = structuredDataMakeTerritory($fixture['countryId'], 'Attraction Town');
    $typeId = structuredDataMakeType('attraction', 'place');
    $object = structuredDataMakeObject($fixture['countryId'], $territoryId, $typeId, 'Old Fortress');

    $this->get("/en/o/{$object['slug']}")
        ->assertOk()
        ->assertSee('"@type":"Place"', false);
});

it('never emits offer availability on an object page while the booking module is inactive for it', function (): void {
    $fixture = structuredDataRegistry();
    $territoryId = structuredDataMakeTerritory($fixture['countryId'], 'No Booking Town');
    $typeId = structuredDataMakeType('hotel', 'lodging', hasRooms: true);
    $object = structuredDataMakeObject($fixture['countryId'], $territoryId, $typeId, 'Unbookable Hotel');

    // No `modules` row for `booking` at all — ModuleResolver treats an
    // unknown key as disabled, matching a portal where the module was
    // never activated.
    $this->get("/en/o/{$object['slug']}")
        ->assertOk()
        ->assertDontSee('makesOffer', false);
});

it('emits offer availability on an object page once the booking module is active for it', function (): void {
    $fixture = structuredDataRegistry();
    $territoryId = structuredDataMakeTerritory($fixture['countryId'], 'Booking Town');
    $typeId = structuredDataMakeType('hotel', 'lodging', hasRooms: true);
    $object = structuredDataMakeObject($fixture['countryId'], $territoryId, $typeId, 'Bookable Hotel');

    DB::table('modules')->insert([
        'key' => 'booking', 'default_state' => 'enabled', 'scopable_levels' => json_encode(['portal']),
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->get("/en/o/{$object['slug']}")
        ->assertOk()
        ->assertSee('makesOffer', false);
});

it('emits a Place entity plus an item list of contained objects on a territory page', function (): void {
    $fixture = structuredDataRegistry();
    $territoryId = structuredDataMakeTerritory($fixture['countryId'], 'Listed Town');
    $typeId = structuredDataMakeType('hotel', 'lodging', hasRooms: true);
    structuredDataMakeObject($fixture['countryId'], $territoryId, $typeId, 'Listed Hotel');

    $this->get('/en/md/listed-town')
        ->assertOk()
        ->assertSee('"@type":"Place"', false)
        ->assertSee('"@type":"ItemList"', false)
        ->assertSee('Listed Hotel', false);
});

it('carries a BreadcrumbList on a page below home, and none on the home page itself', function (): void {
    $fixture = structuredDataRegistry();
    structuredDataMakeTerritory($fixture['countryId'], 'Breadcrumb Town');

    $this->get('/en/md/breadcrumb-town')->assertOk()->assertSee('"@type":"BreadcrumbList"', false);
    $this->get('/en')->assertOk()->assertDontSee('BreadcrumbList', false);
});
