<?php

declare(strict_types=1);

use App\Livewire\Public\CatalogSearch;
use App\Models\ApiClient;
use App\Models\Module;
use App\Models\User;
use App\Services\Api\ApiTokenService;
use App\Services\Modules\ModuleAdministrator;
use App\Support\Api\ApiResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Public API — Parity With the Catalog Page
|--------------------------------------------------------------------------
|
| ObjectController::index() and CatalogSearch both call
| CatalogQueryService::search() — the one shared retrieval contract — but
| that shared implementation is exactly what makes it easy to assume parity
| without ever proving it end to end. This file drives both surfaces
| through their own real entry point (the Livewire component's own render
| cycle, the API's own HTTP request) rather than asserting against the
| service directly, so a future change that special-cases either caller —
| an extra filter only one of them applies, a scope object that stops being
| a true no-op when "unrestricted" — is caught here rather than assumed
| away.
|
| Run against DemoVolumeSeeder's realistic volume, not a handful of
| fixtures: every compared object carries its own globally unique placement
| rank (see makeMarker()), so the ordering PlacementOrderingService applies
| is provably tie-free among the compared rows regardless of how the tens
| of thousands of untiered background objects are physically stored on
| disk — the two surfaces run two independent query executions (the shared
| result cache keys them differently, since one carries a null constraint
| and the other an unrestricted ScopeConstraint object), and an unresolved
| tie could let Postgres return a different row order to each without
| either execution being wrong.
|
| Two records are deliberately never public (one pending moderation, one
| hidden) and given the very lowest ranks of the entire fixture, so a
| visibility regression that leaked them would surface at the very top of
| whichever permutation's page it leaked into — checked directly on both
| surfaces independently, not only via their cross-surface equality, since
| a bug shared by both callers would otherwise still agree with itself.
|
| Tagged `slow`: seeds the full demo volume, excluded from
| `composer test`/`quality`, run explicitly.
|
*/

/** @return array{countryId: int, territoryLevelId: int} */
function apiParityRegistry(): array
{
    $countryId = DB::table('countries')->where('code', 'MD')->value('id');
    $territoryLevelId = DB::table('territory_levels')
        ->where('country_id', $countryId)
        ->orderBy('depth_rank')
        ->value('id');

    return ['countryId' => $countryId, 'territoryLevelId' => $territoryLevelId];
}

/**
 * A single, dedicated, childless territory — never part of DemoVolumeSeeder's
 * own tree (created after it seeds), so a territory filter against it can
 * only ever match objects this file itself inserts.
 */
function apiParityMakeTerritory(int $countryId, int $levelId, string $label): int
{
    $territoryId = DB::table('territories')->insertGetId([
        'country_id' => $countryId, 'level_id' => $levelId, 'parent_id' => null,
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $slug = Str::slug($label);
    DB::table('territory_translations')->insert([
        'territory_id' => $territoryId, 'country_id' => $countryId, 'locale' => 'en',
        'name' => $label, 'slug' => $slug, 'full_slug_path' => $slug,
        'needs_review' => false, 'published_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return $territoryId;
}

/**
 * A single, dedicated object type — created after DemoVolumeSeeder, so its
 * own random type assignment (`array_rand()` over the types that existed at
 * its own run time) can never have handed it to a background object.
 */
function apiParityMakeType(string $key): int
{
    $typeId = DB::table('object_types')->insertGetId([
        'key' => $key, 'is_active' => true, 'attribute_schema' => json_encode([]),
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('object_type_translations')->insert([
        'object_type_id' => $typeId, 'locale' => 'en', 'name' => ucfirst($key), 'slug' => $key,
        'needs_review' => false, 'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);

    return $typeId;
}

/** @param  array<string, mixed>  $overrides */
function apiParityMakeObject(int $countryId, int $territoryId, int $typeId, string $name, array $overrides = []): int
{
    $objectId = DB::table('objects')->insertGetId(array_merge([
        'ulid' => (string) Str::ulid(),
        'owner_id' => User::factory()->create()->id,
        'object_type_id' => $typeId,
        'territory_id' => $territoryId,
        'country_id' => $countryId,
        'status' => 'published', 'moderation_status' => 'approved',
        'created_at' => now(), 'updated_at' => now(),
    ], $overrides));

    DB::table('object_translations')->insert([
        'object_id' => $objectId, 'locale' => 'en', 'name' => $name,
        'slug' => Str::slug($name).'-'.$objectId, 'needs_review' => false, 'published_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return $objectId;
}

/**
 * Gives $objectId its own brand-new placement tier at $rank — never a tier
 * shared with another object in this file, so two marker objects can never
 * tie on `coalesce(pt.rank, 999)`, the ordering contract's dominant term.
 * Every untiered background object coalesces to 999, well behind any rank
 * assigned here.
 */
function apiParityGivePlacement(int $objectId, int $rank): void
{
    $tierId = DB::table('placement_tiers')->insertGetId([
        'rank' => $rank, 'border_colour' => '#000000', 'badge_colour' => '#000000',
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $packageId = DB::table('placement_packages')->insertGetId([
        'placement_tier_id' => $tierId, 'price' => 10, 'currency' => 'EUR',
        'validity_days' => 30, 'bump_allowed' => false, 'is_active' => true,
        'display_order' => $rank, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('object_placements')->insert([
        'object_id' => $objectId, 'placement_package_id' => $packageId,
        'starts_at' => now()->subDay()->toDateString(), 'ends_at' => now()->addDays(30)->toDateString(),
        'internal_priority' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function apiParityEnableModule(User $actor): void
{
    /** @var Module $module */
    $module = Module::query()->where('key', 'api')->firstOrFail();

    app(ModuleAdministrator::class)->setState($module, 'portal', null, true, $actor);
}

function apiParityUnrestrictedToken(User $actor): string
{
    $client = ApiClient::create(['name' => 'Parity Test Client', 'is_active' => true, 'created_by' => $actor->id]);

    $newAccessToken = app(ApiTokenService::class)->issue(
        $client, 'parity-test', [ApiResource::Objects], [], [], null, null, $actor,
    );

    return $newAccessToken->plainTextToken;
}

it('never gives the API a different object set or order than the catalog page renders, at seeded volume', function (): void {
    $this->seed();
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\DemoVolumeSeeder', '--force' => true]);

    $objectCount = DB::table('objects')->count();

    // A dozen fixtures cannot reproduce an ordering divergence that only
    // becomes possible once ties in the ordering columns exist — the same
    // guard PlacementOrderingVolumeTest applies to its own seeded run.
    expect($objectCount)->toBeGreaterThanOrEqual(50_000);

    $actor = User::factory()->create();
    apiParityEnableModule($actor);
    $token = apiParityUnrestrictedToken($actor);

    $registry = apiParityRegistry();
    $territoryUnfiltered = apiParityMakeTerritory($registry['countryId'], $registry['territoryLevelId'], 'Parity Unfiltered Territory');
    $typeUnfiltered = apiParityMakeType('parity-unfiltered-type');
    $territoryA = apiParityMakeTerritory($registry['countryId'], $registry['territoryLevelId'], 'Parity Scoped Territory');
    $typeA = apiParityMakeType('parity-scoped-type');

    // Starts past PlacementTierSeeder's own four launch ranks (1-4, still
    // present via $this->seed()) and stays well short of 999, the fallback
    // PlacementOrderingService::apply() coalesces an untiered object's rank
    // to — every marker below must outrank that fallback, not collide with
    // ranks a real seeded tier already claims (rank carries a unique index).
    $rank = 4;

    // Deliberately never public, and given the two lowest ranks of the
    // whole fixture (1, 2) — the most detectable position possible in any
    // permutation they might structurally match, since both permutations
    // that filter by $territoryA or $typeA would otherwise place them
    // first.
    $pendingId = apiParityMakeObject($registry['countryId'], $territoryA, $typeA, 'Parity Pending Object', ['moderation_status' => 'pending']);
    apiParityGivePlacement($pendingId, ++$rank);
    $hiddenId = apiParityMakeObject($registry['countryId'], $territoryA, $typeA, 'Parity Hidden Object', ['status' => 'hidden']);
    apiParityGivePlacement($hiddenId, ++$rank);

    // Permutation 1: unfiltered first page. 24 uniquely ranked markers —
    // a full page's worth — so every one of page 1's slots is tie-free
    // regardless of the 50,000+ untiered background objects.
    for ($i = 0; $i < 24; $i++) {
        $id = apiParityMakeObject($registry['countryId'], $territoryUnfiltered, $typeUnfiltered, "Parity Unfiltered Marker {$i}");
        apiParityGivePlacement($id, ++$rank);
    }

    // Permutation 2: territory-only. Matches $territoryA regardless of
    // type — deliberately given $typeUnfiltered (never $typeA) so it can
    // never also satisfy the type-only or combined permutations below.
    for ($i = 0; $i < 6; $i++) {
        $id = apiParityMakeObject($registry['countryId'], $territoryA, $typeUnfiltered, "Parity Territory-Only Marker {$i}");
        apiParityGivePlacement($id, ++$rank);
    }

    // Permutation 3: type-only. Matches $typeA regardless of territory —
    // deliberately given $territoryUnfiltered (never $territoryA) so it
    // can never also satisfy the territory-only or combined permutations.
    for ($i = 0; $i < 6; $i++) {
        $id = apiParityMakeObject($registry['countryId'], $territoryUnfiltered, $typeA, "Parity Type-Only Marker {$i}");
        apiParityGivePlacement($id, ++$rank);
    }

    // Permutation 4: territory + type combined. Only these markers satisfy
    // both conditions at once — group 2 fails the type condition, group 3
    // fails the territory condition, by construction above.
    for ($i = 0; $i < 6; $i++) {
        $id = apiParityMakeObject($registry['countryId'], $territoryA, $typeA, "Parity Combined Marker {$i}");
        apiParityGivePlacement($id, ++$rank);
    }

    // Drives both surfaces through their own real entry point — the
    // Livewire component's own render cycle, the API's own HTTP request —
    // never CatalogQueryService directly, which would prove nothing about
    // whether the two callers actually agree.
    $compare = function (?int $territoryId, ?int $typeId) use ($token): array {
        $catalogTest = Livewire::test(CatalogSearch::class);

        if ($territoryId !== null) {
            $catalogTest->set('territoryId', $territoryId);
        }

        if ($typeId !== null) {
            $catalogTest->set('type', $typeId);
        }

        /** @var LengthAwarePaginator $paginator */
        $paginator = $catalogTest->viewData('results');
        $catalogIds = $paginator->getCollection()->pluck('id')->all();

        $params = array_filter(['territory' => $territoryId, 'type' => $typeId], fn ($value): bool => $value !== null);
        $query = $params !== [] ? '?'.http_build_query($params) : '';

        $apiIds = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/objects'.$query)
            ->assertOk()
            ->json('data.*.id');

        return [$catalogIds, $apiIds];
    };

    // --- Permutation 1: unfiltered first page -------------------------------
    [$catalogIds, $apiIds] = $compare(null, null);

    expect($apiIds)->toBe($catalogIds)
        ->and($catalogIds)->toHaveCount(24)
        ->and($catalogIds)->not->toContain($pendingId, $hiddenId)
        ->and($apiIds)->not->toContain($pendingId, $hiddenId);

    // --- Permutation 2: territory filter -------------------------------------
    // Territory alone does not narrow by type, so this also matches the
    // combined group below (also $territoryA): 6 territory-only markers +
    // 6 combined markers = 12. The territory + type permutation is what
    // isolates the combined group down to its own 6.
    [$catalogIds, $apiIds] = $compare($territoryA, null);

    expect($apiIds)->toBe($catalogIds)
        ->and($catalogIds)->toHaveCount(12)
        ->and($catalogIds)->not->toContain($pendingId, $hiddenId)
        ->and($apiIds)->not->toContain($pendingId, $hiddenId);

    // --- Permutation 3: type filter -------------------------------------------
    // Symmetric with permutation 2: type alone also matches the combined
    // group (also $typeA), so 6 type-only markers + 6 combined markers = 12.
    [$catalogIds, $apiIds] = $compare(null, $typeA);

    expect($apiIds)->toBe($catalogIds)
        ->and($catalogIds)->toHaveCount(12)
        ->and($catalogIds)->not->toContain($pendingId, $hiddenId)
        ->and($apiIds)->not->toContain($pendingId, $hiddenId);

    // --- Permutation 4: territory + type combined -----------------------------
    [$catalogIds, $apiIds] = $compare($territoryA, $typeA);

    expect($apiIds)->toBe($catalogIds)
        ->and($catalogIds)->toHaveCount(6)
        ->and($catalogIds)->not->toContain($pendingId, $hiddenId)
        ->and($apiIds)->not->toContain($pendingId, $hiddenId);
})->group('slow');
