<?php

declare(strict_types=1);

use App\Models\Object_;
use App\Models\User;
use App\Services\Seo\IndexationPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Indexation Policy — Matrix, Filter Allowlist, Pagination, Robots (T-6B02)
|--------------------------------------------------------------------------
|
| Filtered catalog views are not indexable by default. A single active
| filter becomes indexable only once an administrator promotes that exact
| value; two or more active filters, or any free-text search, is never
| indexable regardless of promotions. Catalog pagination beyond page 1
| stays indexable and canonicalizes to itself, not to page 1. Non-public
| objects 404 (and therefore never render indexable metadata). robots.txt
| disallows the owner cabinet and back office.
|
*/

/** @return array{languageId: int, countryId: int, hotelTypeId: int} */
function indexationRegistry(): array
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
    $hotelTypeId = DB::table('object_types')->insertGetId([
        'key' => 'hotel', 'is_active' => true, 'has_rooms' => false, 'has_availability_status' => false,
        'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('object_type_translations')->insert([
        'object_type_id' => $hotelTypeId, 'locale' => 'en', 'name' => 'Hotel', 'slug' => 'hotel',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return compact('languageId', 'countryId', 'hotelTypeId');
}

/** @param  array<string, mixed>  $overrides */
function indexationMakeTerritory(int $countryId, string $name, array $overrides = []): int
{
    $levelId = DB::table('territory_levels')->insertGetId([
        'country_id' => $countryId, 'depth_rank' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $territoryId = DB::table('territories')->insertGetId([
        'country_id' => $countryId, 'level_id' => $levelId, 'is_active' => true,
        'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $slug = Str::slug($name);

    DB::table('territory_translations')->insert(array_merge([
        'territory_id' => $territoryId, 'country_id' => $countryId, 'locale' => 'en', 'name' => $name,
        'slug' => $slug, 'full_slug_path' => $slug, 'needs_review' => false, 'published_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ], $overrides));

    return $territoryId;
}

/** @param  array<string, mixed>  $objectOverrides */
function indexationMakeObject(int $countryId, int $territoryId, int $typeId, string $name, array $objectOverrides = []): Object_
{
    $objectId = DB::table('objects')->insertGetId(array_merge([
        'ulid' => (string) Str::ulid(), 'owner_id' => User::factory()->create()->id,
        'object_type_id' => $typeId, 'territory_id' => $territoryId, 'country_id' => $countryId,
        'status' => 'published', 'moderation_status' => 'approved',
        'created_at' => now(), 'updated_at' => now(),
    ], $objectOverrides));
    DB::table('object_translations')->insert([
        'object_id' => $objectId, 'locale' => 'en', 'name' => $name,
        'slug' => Str::slug($name).'-'.$objectId, 'needs_review' => false, 'published_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    /** @var Object_ $object */
    $object = Object_::query()->findOrFail($objectId);

    return $object;
}

it('indexes the plain, unfiltered catalog view by default', function (): void {
    indexationRegistry();

    $this->get('/en/catalog')->assertOk()->assertDontSee('name="robots"', false);
});

it('does not index a single-filter catalog view until an administrator promotes it', function (): void {
    $fixture = indexationRegistry();

    $this->get("/en/catalog?type={$fixture['hotelTypeId']}")
        ->assertOk()
        ->assertSee('name="robots" content="noindex"', false);

    DB::table('catalog_filter_promotions')->insert([
        'signature' => "type={$fixture['hotelTypeId']}", 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    // A raw insert bypasses the write path an eventual administration
    // screen would use — this simulates what that screen must do on every
    // write: drop IndexationPolicy's own cached promotion list.
    app(IndexationPolicy::class)->forgetCache();

    $this->get("/en/catalog?type={$fixture['hotelTypeId']}")
        ->assertOk()
        ->assertDontSee('name="robots"', false);
});

it('never indexes a catalog view with two or more active filters, even if each is individually promoted', function (): void {
    $fixture = indexationRegistry();
    $territoryId = indexationMakeTerritory($fixture['countryId'], 'Two Filter Town');

    DB::table('catalog_filter_promotions')->insert([
        ['signature' => "type={$fixture['hotelTypeId']}", 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ['signature' => "territory={$territoryId}", 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $this->get("/en/catalog?type={$fixture['hotelTypeId']}&territoryId={$territoryId}")
        ->assertOk()
        ->assertSee('name="robots" content="noindex"', false);
});

it('never indexes a catalog view carrying a free-text search, regardless of promotions', function (): void {
    $fixture = indexationRegistry();

    DB::table('catalog_filter_promotions')->insert([
        'signature' => "type={$fixture['hotelTypeId']}", 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->get("/en/catalog?type={$fixture['hotelTypeId']}&q=spa")
        ->assertOk()
        ->assertSee('name="robots" content="noindex"', false);
});

it('keeps catalog pagination beyond page 1 indexable and canonical to itself, not to page 1', function (): void {
    indexationRegistry();

    $response = $this->get('/en/catalog?page=2');

    $response->assertOk()
        ->assertDontSee('name="robots"', false)
        ->assertSee('<link rel="canonical" href="', false)
        ->assertSee('page=2', false);
});

it('404s a non-published object profile with a noindex directive, never exposing its metadata', function (): void {
    $fixture = indexationRegistry();
    $territoryId = indexationMakeTerritory($fixture['countryId'], 'Draft Territory');
    $object = indexationMakeObject($fixture['countryId'], $territoryId, $fixture['hotelTypeId'], 'Draft Hotel', ['status' => 'draft']);

    $slug = DB::table('object_translations')->where('object_id', $object->id)->value('slug');

    $this->get("/en/o/{$slug}")
        ->assertNotFound()
        ->assertSee('name="robots" content="noindex"', false);
});

it('disallows the owner cabinet and back office in robots.txt', function (): void {
    $response = $this->get('/robots.txt');

    $response->assertOk()
        ->assertSee('Disallow: /'.config('booking.panels.admin.path'), false)
        ->assertSee('Disallow: /'.config('booking.panels.cabinet.path'), false);
});
