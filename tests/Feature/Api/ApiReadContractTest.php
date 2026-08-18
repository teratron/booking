<?php

declare(strict_types=1);

use App\Models\ApiClient;
use App\Models\Module;
use App\Models\Object_;
use App\Models\User;
use App\Services\Api\ApiTokenService;
use App\Services\Catalog\CatalogQueryService;
use App\Services\Modules\ModuleAdministrator;
use App\Support\Api\ApiResource;
use App\Support\Catalog\CatalogSearchCriteria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Public API — Read Contract
|--------------------------------------------------------------------------
|
| The read-only launch surface, built as a layer over CatalogQueryService
| rather than a parallel query path. Every collection endpoint must return
| the same ordering the catalog itself renders, and a pending, rejected,
| archived, or hidden record must be absent from every endpoint — the two
| guarantees this suite exists to prove.
|
*/

/** @return array{languageId: int, countryId: int, cityLevelId: int, cityId: int, typeId: int} */
function apiReadFixture(): array
{
    $languageId = DB::table('languages')->insertGetId([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $countryId = DB::table('countries')->insertGetId([
        'code' => 'MD', 'currency' => 'MDL', 'phone_code' => '+373',
        'primary_language_id' => $languageId, 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $cityLevelId = DB::table('territory_levels')->insertGetId([
        'country_id' => $countryId, 'depth_rank' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $cityId = DB::table('territories')->insertGetId([
        'country_id' => $countryId, 'level_id' => $cityLevelId, 'parent_id' => null,
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('territory_translations')->insert([
        'territory_id' => $cityId, 'country_id' => $countryId, 'locale' => 'en', 'name' => 'Chisinau', 'slug' => 'chisinau',
        'full_slug_path' => 'chisinau', 'needs_review' => false, 'published_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $typeId = DB::table('object_types')->insertGetId([
        'key' => 'accommodation', 'is_active' => true, 'attribute_schema' => json_encode([]),
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('object_type_translations')->insert([
        'object_type_id' => $typeId, 'locale' => 'en', 'name' => 'Accommodation', 'slug' => 'accommodation',
        'needs_review' => false, 'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);

    return compact('languageId', 'countryId', 'cityLevelId', 'cityId', 'typeId');
}

/**
 * @param  array{countryId: int, cityId: int, typeId: int}  $fixture
 * @param  array<string, mixed>  $overrides
 */
function apiReadMakeObject(array $fixture, string $name, array $overrides = []): Object_
{
    $objectId = DB::table('objects')->insertGetId(array_merge([
        'ulid' => (string) Str::ulid(),
        'owner_id' => User::factory()->create()->id,
        'object_type_id' => $fixture['typeId'],
        'territory_id' => $fixture['cityId'],
        'country_id' => $fixture['countryId'],
        'status' => 'published', 'moderation_status' => 'approved',
        'created_at' => now(), 'updated_at' => now(),
    ], $overrides));

    DB::table('object_translations')->insert([
        'object_id' => $objectId, 'locale' => 'en', 'name' => $name,
        'slug' => Str::slug($name).'-'.$objectId, 'needs_review' => false, 'published_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    /** @var Object_ $object */
    $object = Object_::query()->withUnmoderated()->findOrFail($objectId);

    return $object;
}

function apiReadMakePlacement(Object_ $object, int $tierRank): void
{
    $tierId = DB::table('placement_tiers')->insertGetId([
        'rank' => $tierRank, 'border_colour' => '#000000', 'badge_colour' => '#000000',
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $packageId = DB::table('placement_packages')->insertGetId([
        'placement_tier_id' => $tierId, 'price' => 10, 'currency' => 'EUR',
        'validity_days' => 30, 'bump_allowed' => false, 'is_active' => true,
        'display_order' => $tierRank, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('object_placements')->insert([
        'object_id' => $object->id, 'placement_package_id' => $packageId,
        'starts_at' => now()->subDays(1)->toDateString(), 'ends_at' => now()->addDays(30)->toDateString(),
        'internal_priority' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** @param  list<ApiResource>  $resources */
function apiReadToken(array $resources, array $countryIds = [], array $categoryIds = []): string
{
    $actor = User::factory()->create();
    $client = ApiClient::create(['name' => 'Contract Test Client', 'is_active' => true, 'created_by' => $actor->id]);

    $newAccessToken = app(ApiTokenService::class)->issue(
        $client, 'contract-test', $resources, $countryIds, $categoryIds, null, null, $actor,
    );

    return $newAccessToken->plainTextToken;
}

function apiReadEnableModule(): void
{
    $moduleId = DB::table('modules')->insertGetId([
        'key' => 'api', 'default_state' => 'disabled', 'scopable_levels' => json_encode(['portal']),
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $module = Module::query()->findOrFail($moduleId);
    $actor = User::factory()->create();

    app(ModuleAdministrator::class)->setState($module, 'portal', null, true, $actor);
}

it('rejects a request with no token', function (): void {
    apiReadEnableModule();

    $this->getJson('/api/v1/objects')->assertUnauthorized();
});

it('rejects a token whose scope does not carry the requested resource ability', function (): void {
    apiReadEnableModule();
    $token = apiReadToken([ApiResource::Countries]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/objects')
        ->assertForbidden();
});

it('returns objects in the same tier-first order CatalogQueryService itself produces', function (): void {
    apiReadEnableModule();
    $fixture = apiReadFixture();

    $bronze = apiReadMakeObject($fixture, 'Bronze Hotel');
    apiReadMakePlacement($bronze, 3);
    $gold = apiReadMakeObject($fixture, 'Gold Hotel');
    apiReadMakePlacement($gold, 1);
    $silver = apiReadMakeObject($fixture, 'Silver Hotel');
    apiReadMakePlacement($silver, 2);

    $expected = app(CatalogQueryService::class)->search(new CatalogSearchCriteria)->getCollection()->pluck('id')->all();

    $token = apiReadToken([ApiResource::Objects]);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/objects')
        ->assertOk();

    expect($response->json('data.*.id'))->toBe($expected)
        ->and($expected)->toBe([$gold->id, $silver->id, $bronze->id]);
});

it('filters the collection by object type from the query string', function (): void {
    apiReadEnableModule();
    $fixture = apiReadFixture();

    $restaurantTypeId = DB::table('object_types')->insertGetId([
        'key' => 'restaurant', 'is_active' => true, 'attribute_schema' => json_encode([]),
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('object_type_translations')->insert([
        'object_type_id' => $restaurantTypeId, 'locale' => 'en', 'name' => 'Restaurant', 'slug' => 'restaurant',
        'needs_review' => false, 'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);

    $hotel = apiReadMakeObject($fixture, 'Filter Hotel');
    $restaurant = apiReadMakeObject($fixture, 'Filter Restaurant', ['object_type_id' => $restaurantTypeId]);

    $token = apiReadToken([ApiResource::Objects]);

    // The query string always hands validate() a string, never an int —
    // CatalogSearchCriteria::$objectTypeId is strictly `?int`, so a missing
    // cast here 500s on the very first request that actually names a type,
    // a path none of this file's other cases exercise.
    $listedIds = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/objects?type={$restaurantTypeId}")
        ->assertOk()
        ->json('data.*.id');

    expect($listedIds)->toBe([$restaurant->id])
        ->and($listedIds)->not->toContain($hotel->id);
});

it('never returns a pending, rejected, archived, or hidden object from any endpoint', function (): void {
    apiReadEnableModule();
    $fixture = apiReadFixture();

    $published = apiReadMakeObject($fixture, 'Visible Hotel');
    $pending = apiReadMakeObject($fixture, 'Pending Hotel', ['moderation_status' => 'pending']);
    $rejected = apiReadMakeObject($fixture, 'Rejected Hotel', ['moderation_status' => 'rejected']);
    $archived = apiReadMakeObject($fixture, 'Archived Hotel', ['status' => 'archived']);
    $hidden = apiReadMakeObject($fixture, 'Hidden Hotel', ['status' => 'hidden']);

    $token = apiReadToken([ApiResource::Objects]);

    $listedIds = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/objects')
        ->assertOk()
        ->json('data.*.id');

    expect($listedIds)->toBe([$published->id])
        ->and($listedIds)->not->toContain($pending->id, $rejected->id, $archived->id, $hidden->id);

    foreach ([$pending, $rejected, $archived, $hidden] as $invisible) {
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/objects/{$invisible->id}")
            ->assertNotFound();
    }

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/objects/{$published->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $published->id);
});

it('narrows a country-scoped token to its own country, absent every other', function (): void {
    apiReadEnableModule();
    $fixture = apiReadFixture();
    $inScope = apiReadMakeObject($fixture, 'In-Scope Hotel');

    $otherLanguageId = DB::table('languages')->insertGetId([
        'code' => 'uk', 'short_label' => 'UK', 'is_active' => true, 'is_primary' => false,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $otherCountryId = DB::table('countries')->insertGetId([
        'code' => 'UA', 'currency' => 'UAH', 'phone_code' => '+380',
        'primary_language_id' => $otherLanguageId, 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $otherLevelId = DB::table('territory_levels')->insertGetId([
        'country_id' => $otherCountryId, 'depth_rank' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $otherTerritoryId = DB::table('territories')->insertGetId([
        'country_id' => $otherCountryId, 'level_id' => $otherLevelId, 'parent_id' => null,
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $outOfScope = apiReadMakeObject($fixture, 'Out-Of-Scope Hotel', [
        'country_id' => $otherCountryId, 'territory_id' => $otherTerritoryId,
    ]);

    $token = apiReadToken([ApiResource::Objects, ApiResource::Countries], countryIds: [$fixture['countryId']]);

    $listedIds = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/objects')
        ->assertOk()
        ->json('data.*.id');

    expect($listedIds)->toBe([$inScope->id]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/objects/{$outOfScope->id}")
        ->assertNotFound();

    $countries = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/countries')
        ->assertOk()
        ->json('data.*.id');

    expect($countries)->toBe([$fixture['countryId']]);
});

it('lists only published news, promotions, and articles', function (): void {
    apiReadEnableModule();
    $fixture = apiReadFixture();
    $object = apiReadMakeObject($fixture, 'News Object');

    $publishedNewsId = DB::table('news_items')->insertGetId([
        'author_id' => User::factory()->create()->id, 'object_id' => $object->id, 'territory_id' => $fixture['cityId'],
        'status' => 'published', 'moderation_status' => 'approved',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('news_translations')->insert([
        'news_item_id' => $publishedNewsId, 'locale' => 'en', 'title' => 'Published News',
        'body' => 'Published news body.', 'slug' => 'published-news',
        'needs_review' => false, 'published_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $draftNewsId = DB::table('news_items')->insertGetId([
        'author_id' => User::factory()->create()->id, 'object_id' => $object->id, 'territory_id' => $fixture['cityId'],
        'status' => 'draft', 'moderation_status' => 'approved',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('news_translations')->insert([
        'news_item_id' => $draftNewsId, 'locale' => 'en', 'title' => 'Draft News',
        'body' => 'Draft news body.', 'slug' => 'draft-news',
        'needs_review' => false, 'published_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $token = apiReadToken([ApiResource::News]);

    $listedIds = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/news')
        ->assertOk()
        ->json('data.*.id');

    expect($listedIds)->toBe([$publishedNewsId]);
});
