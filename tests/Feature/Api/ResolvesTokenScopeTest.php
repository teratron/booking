<?php

declare(strict_types=1);

use App\Models\ApiClient;
use App\Models\Module;
use App\Models\User;
use App\Services\Api\ApiTokenService;
use App\Services\Modules\ModuleAdministrator;
use App\Support\Api\ApiResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| ResolvesTokenScope — country narrowing via a territory relation
|--------------------------------------------------------------------------
|
| News and promotions carry no `country_id` column of their own, so
| ResolvesTokenScope::applyCountryScopeViaTerritory() narrows them through
| the model's own `territory` relation instead of ResourceQueryScoper's
| direct-column narrowing. Exercised at the Feature level, through the real
| controllers and a real issued token: this codebase has no precedent for
| instantiating a controller Concern directly (no tests/Unit/*Controller*
| exists), and country-scoped narrowing is already proven this way for
| ObjectController in tests/Feature/Api/ApiReadContractTest.php — this file
| covers the same behaviour for the territory-relation variant, which that
| suite's own country-scoping case never reaches (it only exercises
| Objects, which carries its own `country_id` column and never touches
| applyCountryScopeViaTerritory at all).
|
*/

function resolvesScopeEnableModule(): void
{
    $moduleId = DB::table('modules')->insertGetId([
        'key' => 'api', 'default_state' => 'disabled', 'scopable_levels' => json_encode(['portal']),
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $module = Module::query()->findOrFail($moduleId);
    $actor = User::factory()->create();

    app(ModuleAdministrator::class)->setState($module, 'portal', null, true, $actor);
}

/** @return array{countryId: int, territoryId: int} */
function resolvesScopeMakeCountry(int $languageId, string $code, string $currency, string $phoneCode): array
{
    $countryId = DB::table('countries')->insertGetId([
        'code' => $code, 'currency' => $currency, 'phone_code' => $phoneCode,
        'primary_language_id' => $languageId, 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $levelId = DB::table('territory_levels')->insertGetId([
        'country_id' => $countryId, 'depth_rank' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $territoryId = DB::table('territories')->insertGetId([
        'country_id' => $countryId, 'level_id' => $levelId, 'parent_id' => null,
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return ['countryId' => $countryId, 'territoryId' => $territoryId];
}

/**
 * @return array{
 *     languageId: int,
 *     inScope: array{countryId: int, territoryId: int},
 *     outOfScope: array{countryId: int, territoryId: int},
 * }
 */
function resolvesScopeFixture(): array
{
    $languageId = DB::table('languages')->insertGetId([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return [
        'languageId' => $languageId,
        'inScope' => resolvesScopeMakeCountry($languageId, 'MD', 'MDL', '+373'),
        'outOfScope' => resolvesScopeMakeCountry($languageId, 'UA', 'UAH', '+380'),
    ];
}

function resolvesScopeMakeNewsItem(int $authorId, ?int $territoryId, string $title): int
{
    $newsId = DB::table('news_items')->insertGetId([
        'author_id' => $authorId, 'object_id' => null, 'territory_id' => $territoryId,
        'status' => 'published', 'moderation_status' => 'approved',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    DB::table('news_translations')->insert([
        'news_item_id' => $newsId, 'locale' => 'en', 'title' => $title,
        'body' => "{$title} body.", 'slug' => Str::slug($title).'-'.$newsId,
        'needs_review' => false, 'published_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return $newsId;
}

function resolvesScopeMakeObject(int $ownerId, int $objectTypeId, int $territoryId, int $countryId): int
{
    return DB::table('objects')->insertGetId([
        'ulid' => (string) Str::ulid(), 'owner_id' => $ownerId, 'object_type_id' => $objectTypeId,
        'territory_id' => $territoryId, 'country_id' => $countryId,
        'status' => 'published', 'moderation_status' => 'approved',
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function resolvesScopeMakePromotion(int $objectId, int $territoryId, string $title): int
{
    $promotionId = DB::table('promotions')->insertGetId([
        'object_id' => $objectId, 'territory_id' => $territoryId,
        'starts_at' => now()->subDay()->toDateString(), 'ends_at' => now()->addDays(30)->toDateString(),
        'status' => 'published', 'moderation_status' => 'approved',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    DB::table('promotion_translations')->insert([
        'promotion_id' => $promotionId, 'locale' => 'en', 'title' => $title,
        'slug' => Str::slug($title).'-'.$promotionId,
        'needs_review' => false, 'published_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return $promotionId;
}

/** @param  list<ApiResource>  $resources */
function resolvesScopeToken(array $resources, array $countryIds = []): string
{
    $actor = User::factory()->create();
    $client = ApiClient::create(['name' => 'Scope Test Client', 'is_active' => true, 'created_by' => $actor->id]);

    $newAccessToken = app(ApiTokenService::class)->issue(
        $client, 'scope-test', $resources, $countryIds, [], null, null, $actor,
    );

    return $newAccessToken->plainTextToken;
}

it('narrows the news feed to the token\'s own country while still admitting portal-wide news with no territory at all', function (): void {
    resolvesScopeEnableModule();
    $fixture = resolvesScopeFixture();
    $author = User::factory()->create();

    $inScopeId = resolvesScopeMakeNewsItem($author->id, $fixture['inScope']['territoryId'], 'In-Scope News');
    $outOfScopeId = resolvesScopeMakeNewsItem($author->id, $fixture['outOfScope']['territoryId'], 'Out-Of-Scope News');
    $portalWideId = resolvesScopeMakeNewsItem($author->id, null, 'Portal-Wide News');

    $token = resolvesScopeToken([ApiResource::News], countryIds: [$fixture['inScope']['countryId']]);

    $listedIds = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/news')
        ->assertOk()
        ->json('data.*.id');

    expect($listedIds)->toEqualCanonicalizing([$inScopeId, $portalWideId])
        ->and($listedIds)->not->toContain($outOfScopeId);
});

it('narrows the promotions feed to the token\'s own country, admitting no row since a promotion always carries a territory', function (): void {
    resolvesScopeEnableModule();
    $fixture = resolvesScopeFixture();
    $owner = User::factory()->create();

    $objectTypeId = DB::table('object_types')->insertGetId([
        'key' => 'accommodation', 'is_active' => true, 'attribute_schema' => json_encode([]),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $inScopeObjectId = resolvesScopeMakeObject($owner->id, $objectTypeId, $fixture['inScope']['territoryId'], $fixture['inScope']['countryId']);
    $outOfScopeObjectId = resolvesScopeMakeObject($owner->id, $objectTypeId, $fixture['outOfScope']['territoryId'], $fixture['outOfScope']['countryId']);

    $inScopeId = resolvesScopeMakePromotion($inScopeObjectId, $fixture['inScope']['territoryId'], 'In-Scope Promotion');
    $outOfScopeId = resolvesScopeMakePromotion($outOfScopeObjectId, $fixture['outOfScope']['territoryId'], 'Out-Of-Scope Promotion');

    $token = resolvesScopeToken([ApiResource::Promotions], countryIds: [$fixture['inScope']['countryId']]);

    $listedIds = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/promotions')
        ->assertOk()
        ->json('data.*.id');

    expect($listedIds)->toBe([$inScopeId])
        ->and($listedIds)->not->toContain($outOfScopeId);
});
