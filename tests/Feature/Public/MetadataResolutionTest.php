<?php

declare(strict_types=1);

use App\Models\ObjectType;
use App\Models\Territory;
use App\Services\Seo\MetadataResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| SEO Metadata Resolution Ladder (T-6B01)
|--------------------------------------------------------------------------
|
| The three-rung ladder every indexable public page reads through before
| it renders: an entity's own explicit seo_* column, else an
| administrator-defined template, else a plain derivation from the
| entity's name and territory. Also asserts the fields that ride alongside
| the ladder rather than through it — canonical URL, indexability, and the
| Open Graph mirror — and that a real page never ships an empty title or
| description.
|
*/

/** @return array{languageId: int, countryId: int} */
function metaResolutionRegistry(): array
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

/** @param  array<string, mixed>  $translationOverrides */
function metaResolutionTerritory(int $countryId, string $name, array $translationOverrides = []): Territory
{
    $levelId = DB::table('territory_levels')->insertGetId([
        'country_id' => $countryId, 'depth_rank' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $territoryId = DB::table('territories')->insertGetId([
        'country_id' => $countryId, 'level_id' => $levelId, 'parent_id' => null, 'is_active' => true,
        'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $slug = Str::slug($name);

    DB::table('territory_translations')->insert(array_merge([
        'territory_id' => $territoryId, 'country_id' => $countryId, 'locale' => 'en', 'name' => $name,
        'slug' => $slug, 'full_slug_path' => $slug, 'needs_review' => false, 'published_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ], $translationOverrides));

    /** @var Territory $territory */
    $territory = Territory::query()->findOrFail($territoryId);

    return $territory;
}

/** @param  array<string, mixed>  $translationOverrides */
function metaResolutionObjectType(string $key, string $name, array $translationOverrides = []): ObjectType
{
    $typeId = DB::table('object_types')->insertGetId([
        'key' => $key, 'is_active' => true, 'has_rooms' => false, 'has_availability_status' => false,
        'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);

    DB::table('object_type_translations')->insert(array_merge([
        'object_type_id' => $typeId, 'locale' => 'en', 'name' => $name, 'slug' => Str::slug($name),
        'created_at' => now(), 'updated_at' => now(),
    ], $translationOverrides));

    /** @var ObjectType $objectType */
    $objectType = ObjectType::query()->findOrFail($typeId);

    return $objectType;
}

function metaResolutionTemplate(string $entityType, string $field, string $template): void
{
    DB::table('seo_metadata_templates')->insert([
        'entity_type' => $entityType, 'locale' => 'en', 'field' => $field, 'template' => $template,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

it('uses the explicit seo_title and seo_description over any template', function (): void {
    $registry = metaResolutionRegistry();
    $territory = metaResolutionTerritory($registry['countryId'], 'Bukovel', [
        'seo_title' => 'Explicit Title', 'seo_description' => 'Explicit description.',
    ]);
    metaResolutionTemplate('territory', 'title', '{name} Travel Guide');
    metaResolutionTemplate('territory', 'description', 'Explore {name}.');

    $metadata = app(MetadataResolver::class)->resolve($territory, 'en');

    expect($metadata->title)->toBe('Explicit Title')
        ->and($metadata->description)->toBe('Explicit description.');
});

it('renders the administrator template when no explicit value is set', function (): void {
    $registry = metaResolutionRegistry();
    $territory = metaResolutionTerritory($registry['countryId'], 'Bukovel');
    metaResolutionTemplate('territory', 'title', '{name} Travel Guide');
    metaResolutionTemplate('territory', 'description', 'Explore {name} today.');

    $metadata = app(MetadataResolver::class)->resolve($territory, 'en');

    expect($metadata->title)->toBe('Bukovel Travel Guide')
        ->and($metadata->description)->toBe('Explore Bukovel today.');
});

it('derives the title and description from the entity name when neither an explicit value nor a template exists', function (): void {
    $registry = metaResolutionRegistry();
    $territory = metaResolutionTerritory($registry['countryId'], 'Bukovel');

    $metadata = app(MetadataResolver::class)->resolve($territory, 'en');

    expect($metadata->title)->toBe('Bukovel')
        ->and($metadata->description)->not->toBeEmpty()
        ->and($metadata->description)->toContain('Bukovel');
});

it('never renders an empty title or meta description on a real territory page, even for a bare entity', function (): void {
    $registry = metaResolutionRegistry();
    metaResolutionTerritory($registry['countryId'], 'Bare Territory');

    $response = $this->get('/en/md/bare-territory');

    $response->assertOk()
        ->assertSee('<title>Bare Territory</title>', false)
        ->assertSee('<meta name="description" content="', false);
});

it('mirrors the resolved title and description into Open Graph fields when no explicit Open Graph override exists', function (): void {
    $registry = metaResolutionRegistry();
    $territory = metaResolutionTerritory($registry['countryId'], 'Bukovel', [
        'seo_title' => 'Mirrored Title', 'seo_description' => 'Mirrored description.',
    ]);

    $metadata = app(MetadataResolver::class)->resolve($territory, 'en');

    expect($metadata->ogTitle)->toBe('Mirrored Title')
        ->and($metadata->ogDescription)->toBe('Mirrored description.');
});

it('uses an explicit Open Graph override instead of mirroring the resolved title and description', function (): void {
    $registry = metaResolutionRegistry();
    $territory = metaResolutionTerritory($registry['countryId'], 'Bukovel', [
        'seo_title' => 'Page Title', 'seo_description' => 'Page description.',
        'seo_og_title' => 'Social Title', 'seo_og_description' => 'Social description.',
    ]);

    $metadata = app(MetadataResolver::class)->resolve($territory, 'en');

    expect($metadata->ogTitle)->toBe('Social Title')
        ->and($metadata->ogDescription)->toBe('Social description.');
});

it('emits a noindex robots directive only when an entity is explicitly marked not indexable', function (): void {
    $registry = metaResolutionRegistry();
    metaResolutionTerritory($registry['countryId'], 'Default Territory');
    metaResolutionTerritory($registry['countryId'], 'Hidden Territory', ['seo_indexable' => false]);

    $this->get('/en/md/default-territory')->assertOk()->assertDontSee('name="robots"', false);
    $this->get('/en/md/hidden-territory')->assertOk()->assertSee('name="robots" content="noindex"', false);
});

it('falls back to the page own resolved URL for canonical, and honours an explicit override otherwise', function (): void {
    $registry = metaResolutionRegistry();
    metaResolutionTerritory($registry['countryId'], 'Default Canonical');
    metaResolutionTerritory($registry['countryId'], 'Overridden Canonical', [
        'seo_canonical_url' => 'https://example.com/custom-canonical',
    ]);

    $this->get('/en/md/default-canonical')
        ->assertOk()
        ->assertSee('<link rel="canonical" href="', false)
        ->assertSee('/en/md/default-canonical">', false);

    $this->get('/en/md/overridden-canonical')
        ->assertOk()
        ->assertSee('<link rel="canonical" href="https://example.com/custom-canonical">', false);
});

it('resolves the typed-catalog title and description from the object type template composed with the territory name', function (): void {
    $registry = metaResolutionRegistry();
    $territory = metaResolutionTerritory($registry['countryId'], 'Bukovel');
    $objectType = metaResolutionObjectType('hotel', 'Hotels');
    metaResolutionTemplate('object_type', 'title', '{name} in {territory}');
    metaResolutionTemplate('object_type', 'description', 'Browse {name} in {territory}.');

    $metadata = app(MetadataResolver::class)->resolveTypedCatalog($territory, $objectType, 'en');

    expect($metadata->title)->toBe('Hotels in Bukovel')
        ->and($metadata->description)->toBe('Browse Hotels in Bukovel.')
        ->and($metadata->indexable)->toBeTrue();
});

it('derives the typed-catalog title from both names when no object type template exists', function (): void {
    $registry = metaResolutionRegistry();
    $territory = metaResolutionTerritory($registry['countryId'], 'Bukovel');
    $objectType = metaResolutionObjectType('hotel', 'Hotels');

    $metadata = app(MetadataResolver::class)->resolveTypedCatalog($territory, $objectType, 'en');

    expect($metadata->title)->toBe('Hotels — Bukovel');
});
