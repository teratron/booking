<?php

declare(strict_types=1);

use App\Models\Object_;
use App\Models\Territory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Public Shell — hreflang Alternate Links
|--------------------------------------------------------------------------
|
| Every indexable public page declares its alternates in every active
| language, plus one x-default pointing at the primary language, computed
| through the identical LocaleSwitchResolver::targetUrl() call the language
| switcher itself uses — so the two can never independently drift apart.
|
*/

/** @return array{languageId: int, secondLanguageId: int, countryId: int} */
function hreflangRegistry(): array
{
    $languageId = DB::table('languages')->insertGetId([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'display_order' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $secondLanguageId = DB::table('languages')->insertGetId([
        'code' => 'ru', 'short_label' => 'RU', 'is_active' => true, 'is_primary' => false,
        'display_order' => 2, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $countryId = DB::table('countries')->insertGetId([
        'code' => 'MD', 'currency' => 'MDL', 'phone_code' => '+373',
        'primary_language_id' => $languageId, 'is_active' => true, 'display_order' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return compact('languageId', 'secondLanguageId', 'countryId');
}

function hreflangTerritory(int $countryId, string $enName, string $ruName): Territory
{
    $levelId = DB::table('territory_levels')->insertGetId([
        'country_id' => $countryId, 'depth_rank' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $territoryId = DB::table('territories')->insertGetId([
        'country_id' => $countryId, 'level_id' => $levelId, 'parent_id' => null, 'is_active' => true,
        'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $enSlug = Str::slug($enName);
    $ruSlug = Str::slug($ruName);

    DB::table('territory_translations')->insert([
        'territory_id' => $territoryId, 'country_id' => $countryId, 'locale' => 'en', 'name' => $enName,
        'slug' => $enSlug, 'full_slug_path' => $enSlug, 'needs_review' => false, 'published_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('territory_translations')->insert([
        'territory_id' => $territoryId, 'country_id' => $countryId, 'locale' => 'ru', 'name' => $ruName,
        'slug' => $ruSlug, 'full_slug_path' => $ruSlug, 'needs_review' => false, 'published_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    /** @var Territory $territory */
    $territory = Territory::query()->findOrFail($territoryId);

    return $territory;
}

/**
 * Explicit, deliberately unrelated slugs — not derived from the name via
 * `Str::slug()` — since Laravel's transliterator can produce the identical
 * ASCII slug from an EN and a RU name that read as "the same place"
 * (`Str::slug('Bukovel')` and `Str::slug('Буковель')` both resolve to
 * `bukovel`), which is exactly the coincidence that let F-02 ship
 * undetected: a same-slug fixture cannot distinguish "swapped only lang"
 * from "resolved the real translated slug."
 */
function hreflangTerritoryWithDistinctSlugs(int $countryId, string $enSlug, string $ruSlug): Territory
{
    $levelId = DB::table('territory_levels')->insertGetId([
        'country_id' => $countryId, 'depth_rank' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $territoryId = DB::table('territories')->insertGetId([
        'country_id' => $countryId, 'level_id' => $levelId, 'parent_id' => null, 'is_active' => true,
        'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);

    DB::table('territory_translations')->insert([
        'territory_id' => $territoryId, 'country_id' => $countryId, 'locale' => 'en', 'name' => 'EN Territory',
        'slug' => $enSlug, 'full_slug_path' => $enSlug, 'needs_review' => false, 'published_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('territory_translations')->insert([
        'territory_id' => $territoryId, 'country_id' => $countryId, 'locale' => 'ru', 'name' => 'RU Territory',
        'slug' => $ruSlug, 'full_slug_path' => $ruSlug, 'needs_review' => false, 'published_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    /** @var Territory $territory */
    $territory = Territory::query()->findOrFail($territoryId);

    return $territory;
}

function hreflangObjectWithDistinctSlugs(int $territoryId, int $countryId, int $typeId, string $enSlug, string $ruSlug): Object_
{
    $objectId = DB::table('objects')->insertGetId([
        'ulid' => (string) Str::ulid(), 'owner_id' => User::factory()->create()->id,
        'object_type_id' => $typeId, 'territory_id' => $territoryId, 'country_id' => $countryId,
        'status' => 'published', 'moderation_status' => 'approved',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('object_translations')->insert([
        'object_id' => $objectId, 'locale' => 'en', 'name' => 'EN Object',
        'slug' => $enSlug, 'needs_review' => false, 'published_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('object_translations')->insert([
        'object_id' => $objectId, 'locale' => 'ru', 'name' => 'RU Object',
        'slug' => $ruSlug, 'needs_review' => false, 'published_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    /** @var Object_ $object */
    $object = Object_::query()->findOrFail($objectId);

    return $object;
}

it('resolves the language switcher and hreflang alternate to the target locale\'s own translated slug for a territory, not the current locale\'s slug', function (): void {
    // F-02: LocaleSwitchResolver::targetUrl() rebuilt the current route
    // with only `lang` swapped, keeping the current locale's slug — wrong
    // whenever the two locales' slugs actually differ, which the previous
    // test's own EN/RU name pair happened not to exercise (transliteration
    // collapses both to the same ASCII slug).
    $registry = hreflangRegistry();
    hreflangTerritoryWithDistinctSlugs($registry['countryId'], 'en-only-slug', 'ru-only-slug');

    $response = $this->get('/en/md/en-only-slug');

    $response->assertOk()
        ->assertSee('<link rel="alternate" hreflang="ru" href="http://booking.test/ru/md/ru-only-slug">', false)
        ->assertDontSee('href="http://booking.test/ru/md/en-only-slug"', false);

    $this->get('/ru/md/ru-only-slug')->assertOk();
});

it('resolves the language switcher and hreflang alternate to the target locale\'s own translated slug for an object, not the current locale\'s slug', function (): void {
    $registry = hreflangRegistry();
    $territory = hreflangTerritoryWithDistinctSlugs($registry['countryId'], 'object-territory-en', 'object-territory-ru');
    $typeId = DB::table('object_types')->insertGetId([
        'key' => 'hotel', 'is_active' => true, 'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    hreflangObjectWithDistinctSlugs($territory->id, $registry['countryId'], $typeId, 'en-only-object-slug', 'ru-only-object-slug');

    $response = $this->get('/en/o/en-only-object-slug');

    $response->assertOk()
        ->assertSee('<link rel="alternate" hreflang="ru" href="http://booking.test/ru/o/ru-only-object-slug">', false)
        ->assertDontSee('href="http://booking.test/ru/o/en-only-object-slug"', false);

    $this->get('/ru/o/ru-only-object-slug')->assertOk();
});

it('declares an hreflang alternate for every active language, plus one x-default pointing at the primary language', function (): void {
    $registry = hreflangRegistry();
    hreflangTerritory($registry['countryId'], 'Bukovel', 'Буковель');

    $response = $this->get('/en/md/bukovel');

    $response->assertOk()
        ->assertSee('<link rel="alternate" hreflang="en" href="http://booking.test/en/md/bukovel">', false)
        ->assertSee('<link rel="alternate" hreflang="ru" href="http://booking.test/ru/md/', false)
        ->assertSee('<link rel="alternate" hreflang="x-default" href="http://booking.test/en/md/bukovel">', false);

    // Exactly one x-default, not one per language.
    expect(substr_count($response->getContent(), 'hreflang="x-default"'))->toBe(1);
});

it('is reciprocal — the EN page\'s ru alternate, fetched, declares an en alternate pointing back to the same EN page', function (): void {
    $registry = hreflangRegistry();
    hreflangTerritory($registry['countryId'], 'Bukovel', 'Буковель');

    $enResponse = $this->get('/en/md/bukovel')->assertOk();

    preg_match('/hreflang="ru" href="([^"]+)"/', $enResponse->getContent(), $matches);
    $ruUrl = $matches[1] ?? null;

    expect($ruUrl)->not->toBeNull();

    $ruPath = (string) parse_url($ruUrl, PHP_URL_PATH);

    $ruResponse = $this->get($ruPath)->assertOk();

    $ruResponse->assertSee('<link rel="alternate" hreflang="en" href="http://booking.test/en/md/bukovel">', false);
});

it('declares hreflang alternates on the catalog page, carrying the active query string through every alternate', function (): void {
    hreflangRegistry();

    $this->get('/en/catalog?type=5')
        ->assertOk()
        ->assertSee('<link rel="alternate" hreflang="en" href="http://booking.test/en/catalog?type=5">', false)
        ->assertSee('<link rel="alternate" hreflang="ru" href="http://booking.test/ru/catalog?type=5">', false);
});
