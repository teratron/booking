<?php

declare(strict_types=1);

use App\Models\Object_;
use App\Models\Territory;
use App\Models\User;
use App\Services\Geography\TerritoryAdministrator;
use App\Services\Seo\PublicSlugResolver;
use App\Services\Seo\PublicUrlGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| SEO URL Grammar — Addressing Foundation
|--------------------------------------------------------------------------
|
| The inbound (PublicSlugResolver) and outbound (PublicUrlGenerator) halves
| of the territory-hierarchy and flat-object addressing grammar agree with
| each other and with the routes that serve them. Object addressing stays
| stable under a territory reparent — the one operation the flat object
| path exists to survive — and two territories in different countries may
| legitimately share a leaf slug, which the registered unique constraint
| must not reject.
|
*/

/** @return array{languageId: int, mdId: int, geId: int} */
function seoGrammarRegistry(): array
{
    $languageId = DB::table('languages')->insertGetId([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $mdId = DB::table('countries')->insertGetId([
        'code' => 'MD', 'currency' => 'MDL', 'phone_code' => '+373',
        'primary_language_id' => $languageId, 'is_active' => true, 'display_order' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $geId = DB::table('countries')->insertGetId([
        'code' => 'GE', 'currency' => 'GEL', 'phone_code' => '+995',
        'primary_language_id' => $languageId, 'is_active' => true, 'display_order' => 2,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return compact('languageId', 'mdId', 'geId');
}

function seoGrammarMakeTerritory(int $countryId, string $name, ?int $parentId, int $displayOrder = 0): Territory
{
    $levelId = DB::table('territory_levels')->insertGetId([
        'country_id' => $countryId, 'depth_rank' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $territoryId = DB::table('territories')->insertGetId([
        'country_id' => $countryId, 'level_id' => $levelId, 'parent_id' => $parentId, 'is_active' => true,
        'display_order' => $displayOrder, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $slug = Str::slug($name);
    $parentPath = $parentId !== null
        ? DB::table('territory_translations')->where('territory_id', $parentId)->where('locale', 'en')->value('full_slug_path')
        : null;

    DB::table('territory_translations')->insert([
        'territory_id' => $territoryId, 'country_id' => $countryId, 'locale' => 'en', 'name' => $name, 'slug' => $slug,
        'full_slug_path' => $parentPath !== null ? "{$parentPath}/{$slug}" : $slug,
        'needs_review' => false, 'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);

    /** @var Territory $territory */
    $territory = Territory::query()->findOrFail($territoryId);

    return $territory;
}

function seoGrammarMakeType(string $key, string $slug): int
{
    $typeId = DB::table('object_types')->insertGetId([
        'key' => $key, 'is_active' => true, 'has_rooms' => false, 'has_availability_status' => false,
        'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('object_type_translations')->insert([
        'object_type_id' => $typeId, 'locale' => 'en', 'name' => ucfirst($key), 'slug' => $slug,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return $typeId;
}

/** @param  array<string, mixed>  $overrides */
function seoGrammarMakeObject(int $countryId, int $territoryId, int $typeId, string $name, array $overrides = []): Object_
{
    $objectId = DB::table('objects')->insertGetId(array_merge([
        'ulid' => (string) Str::ulid(), 'owner_id' => User::factory()->create()->id,
        'object_type_id' => $typeId, 'territory_id' => $territoryId, 'country_id' => $countryId,
        'status' => 'published', 'moderation_status' => 'approved',
        'created_at' => now(), 'updated_at' => now(),
    ], $overrides));
    DB::table('object_translations')->insert([
        'object_id' => $objectId, 'locale' => 'en', 'name' => $name,
        'slug' => Str::slug($name).'-'.$objectId, 'needs_review' => false, 'published_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    /** @var Object_ $object */
    $object = Object_::query()->findOrFail($objectId);

    return $object;
}

it('resolves a five-level territory path to the right record', function (): void {
    $fixture = seoGrammarRegistry();
    $l1 = seoGrammarMakeTerritory($fixture['mdId'], 'Country Root', null);
    $l2 = seoGrammarMakeTerritory($fixture['mdId'], 'Region', $l1->id);
    $l3 = seoGrammarMakeTerritory($fixture['mdId'], 'District', $l2->id);
    $l4 = seoGrammarMakeTerritory($fixture['mdId'], 'Settlement', $l3->id);

    $url = app(PublicUrlGenerator::class)->territoryUrl($l4);

    expect($url)->not->toBeNull()
        ->and($url)->toContain('/md/country-root/region/district/settlement');

    $response = $this->get($url);

    $response->assertOk()->assertSee('Settlement');
});

it('resolves the typed-catalog segment as a trailing object-type slug on a territory path', function (): void {
    $fixture = seoGrammarRegistry();
    $territory = seoGrammarMakeTerritory($fixture['mdId'], 'Resort Town', null);
    $typeId = seoGrammarMakeType('hotel', 'hotels');
    seoGrammarMakeObject($fixture['mdId'], $territory->id, $typeId, 'Resort Hotel');

    $resolver = app(PublicSlugResolver::class);
    $resolved = $resolver->resolveTerritoryPath('en', 'md', 'resort-town/hotels');

    expect($resolved)->not->toBeNull()
        ->and($resolved['territory']->id)->toBe($territory->id)
        ->and($resolved['objectType']->id)->toBe($typeId);

    $response = $this->get('/en/md/resort-town/hotels');

    $response->assertOk()->assertSee('Resort Hotel');
});

it('keeps an object URL unchanged when the object is reassigned to a different territory', function (): void {
    $fixture = seoGrammarRegistry();
    $originalTerritory = seoGrammarMakeTerritory($fixture['mdId'], 'Original Territory', null);
    $neighbouringResort = seoGrammarMakeTerritory($fixture['mdId'], 'Neighbouring Resort', null);
    $typeId = seoGrammarMakeType('hotel', 'hotel');
    $object = seoGrammarMakeObject($fixture['mdId'], $originalTerritory->id, $typeId, 'Stable Hotel');

    $urlBefore = app(PublicUrlGenerator::class)->objectUrl($object);

    // The exact structural move §5.1 names as the reason object URLs stay
    // flat: an administrator reassigns the object to a neighbouring resort.
    // Under a hierarchical object URL this would break a ranked page.
    $object->update(['territory_id' => $neighbouringResort->id]);

    $urlAfter = app(PublicUrlGenerator::class)->objectUrl($object->fresh());

    expect($urlAfter)->toBe($urlBefore);
    $this->get($urlAfter)->assertOk();
});

it('keeps every object URL under a territory unchanged when that territory is reparented under a different one', function (): void {
    $fixture = seoGrammarRegistry();
    $originalParent = seoGrammarMakeTerritory($fixture['mdId'], 'Original Parent', null);
    $newParent = seoGrammarMakeTerritory($fixture['mdId'], 'New Parent', null);
    $child = seoGrammarMakeTerritory($fixture['mdId'], 'Reparented Child', $originalParent->id);
    $typeId = seoGrammarMakeType('hotel', 'hotel');
    $object = seoGrammarMakeObject($fixture['mdId'], $child->id, $typeId, 'Child Territory Hotel');

    $urlBefore = app(PublicUrlGenerator::class)->objectUrl($object);

    app(TerritoryAdministrator::class)->reparent($child, $newParent->id, User::factory()->create());

    $urlAfter = app(PublicUrlGenerator::class)->objectUrl($object->fresh());

    expect($urlAfter)->toBe($urlBefore);
    $this->get($urlAfter)->assertOk();

    // The territory's own URL, unlike the object's, does change — its
    // full_slug_path is recomputed under the new parent chain.
    $childUrlAfter = app(PublicUrlGenerator::class)->territoryUrl($child->fresh());
    expect($childUrlAfter)->toContain('/md/new-parent/reparented-child');
});

it('allows two territories in different countries to share the same leaf slug', function (): void {
    $fixture = seoGrammarRegistry();
    $mdTerritory = seoGrammarMakeTerritory($fixture['mdId'], 'Centru', null);
    $geTerritory = seoGrammarMakeTerritory($fixture['geId'], 'Centru', null);

    expect($mdTerritory->id)->not->toBe($geTerritory->id);

    $mdUrl = app(PublicUrlGenerator::class)->territoryUrl($mdTerritory);
    $geUrl = app(PublicUrlGenerator::class)->territoryUrl($geTerritory);

    expect($mdUrl)->toContain('/md/centru')->and($geUrl)->toContain('/ge/centru');

    $this->get($mdUrl)->assertOk()->assertSee('Centru');
    $this->get($geUrl)->assertOk()->assertSee('Centru');
});

it('redirects the bare country segment to its own top-level landing territory', function (): void {
    $fixture = seoGrammarRegistry();
    $landing = seoGrammarMakeTerritory($fixture['mdId'], 'Landing Territory', null, 1);
    seoGrammarMakeTerritory($fixture['mdId'], 'Secondary Territory', null, 2);

    $response = $this->get('/en/md');

    $response->assertRedirect(app(PublicUrlGenerator::class)->territoryUrl($landing));
});

it('404s a country segment for a country that does not exist or is inactive', function (): void {
    $this->get('/en/zz')->assertNotFound();
});

it('404s a territory path against the wrong country segment', function (): void {
    $fixture = seoGrammarRegistry();
    $territory = seoGrammarMakeTerritory($fixture['mdId'], 'Moldovan Only', null);

    $this->get('/en/ge/moldovan-only')->assertNotFound();
});
