<?php

declare(strict_types=1);

use App\Models\Object_;
use App\Models\ObjectType;
use App\Models\Territory;
use App\Models\User;
use App\Services\Placement\BumpService;
use App\Services\Placement\PlacementOrderingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Catalog Ordering & Bump Invariants Under Seeded Volume
|--------------------------------------------------------------------------
|
| PlacementOrderingService::apply() is the catalog's single ORDER BY
| contract: `placement_tiers.rank` (ascending — rank 1, the launch VIP tier,
| sorts first) is its dominant term, ahead of pinning, bump recency,
| promotion weight, and recency. PlacementTierSeeder documents that this
| direction was deliberately chosen, and once got built inverted by mistake
| before any real data existed — a dozen fixtures cannot reproduce that
| class of regression, since the join chain this ordering walks (placements,
| packages, tiers, a grouped bump subquery, promotions) behaves differently
| once it actually has tens of thousands of rows to resolve against.
|
| This seeds the full demo volume once, gives every seeded object a tier,
| and proves three things: rank dominance holds across a territory-level and
| object-category scope sweep, a bump's recency term never leaks into a
| sibling scope's ordering, and the ordering query itself stays inside the
| portal's own per-request query budget at this volume.
|
| Tagged `slow`: excluded from `composer test`/`quality`, run explicitly.
|
*/

/**
 * Assigns every seeded object one of the four launch tiers, cycling by
 * insertion order — a property the ordering contract's own later terms
 * (bump recency, promotion weight, created_at) do not depend on — so a
 * passing rank-monotonicity check below reflects the `rank` term itself
 * winning, not a coincidence of how the demo data happens to be shaped.
 *
 * @return array<int, int> placement_package id keyed by tier rank (1 = best, 4 = worst)
 */
function assignLaunchTiersToEverySeededObject(): array
{
    $packageIdByRank = DB::table('placement_packages')
        ->join('placement_tiers', 'placement_tiers.id', '=', 'placement_packages.placement_tier_id')
        ->orderBy('placement_tiers.rank')
        ->pluck('placement_packages.id', 'placement_tiers.rank')
        ->all();

    $ranks = array_keys($packageIdByRank);
    $rankCount = count($ranks);
    $now = now();

    $buffer = [];
    $index = 0;

    foreach (DB::table('objects')->orderBy('id')->pluck('id') as $objectId) {
        $rank = $ranks[$index % $rankCount];
        $index++;

        $buffer[] = [
            'object_id' => $objectId,
            'placement_package_id' => $packageIdByRank[$rank],
            'starts_at' => $now->toDateString(),
            'ends_at' => $now->copy()->addDays(30)->toDateString(),
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if (count($buffer) >= 2000) {
            DB::table('object_placements')->insert($buffer);
            $buffer = [];
        }
    }

    if ($buffer !== []) {
        DB::table('object_placements')->insert($buffer);
    }

    return $packageIdByRank;
}

/**
 * The first territory at $depthRank beneath $countryId. Per
 * TerritoryLevelSeeder's own per-country ladder, depth_rank 1 is each
 * launch country's own top-level division (District/Oblast/Region, directly
 * beneath the country itself — the broadest scope short of the country),
 * depth_rank 2 the next rung down (Municipality/Raion), and depth_rank 3
 * the one below that (Town/City). Objects themselves attach one level
 * deeper still (depth_rank 4); this test's scopes never need to filter the
 * object set — PlacementOrderingService::apply() does not restrict rows by
 * scope, only the bump-recency term reads it — so any depth works as a
 * scope regardless of which objects are geographically "in" it.
 */
function territoryAtDepth(int $countryId, int $depthRank): Territory
{
    $levelId = DB::table('territory_levels')
        ->where('country_id', $countryId)
        ->where('depth_rank', $depthRank)
        ->value('id');

    $territoryId = DB::table('territories')
        ->where('level_id', $levelId)
        ->orderBy('id')
        ->value('id');

    return Territory::query()->findOrFail($territoryId);
}

/**
 * Two depth-rank-3 ("city") territories sharing the same depth-rank-2
 * ("region") parent — the sibling pair the scope-isolation check needs.
 *
 * @return array{0: Territory, 1: Territory}
 */
function siblingCityLevelTerritories(int $countryId): array
{
    $cityLevelId = DB::table('territory_levels')
        ->where('country_id', $countryId)
        ->where('depth_rank', 3)
        ->value('id');

    $parentId = DB::table('territories')
        ->where('level_id', $cityLevelId)
        ->orderBy('id')
        ->value('parent_id');

    $siblingIds = DB::table('territories')
        ->where('level_id', $cityLevelId)
        ->where('parent_id', $parentId)
        ->orderBy('id')
        ->limit(2)
        ->pluck('id');

    expect($siblingIds)->toHaveCount(
        2,
        'The demo territory tree did not produce two sibling city-level territories to test scope isolation against.'
    );

    return [
        Territory::query()->findOrFail($siblingIds[0]),
        Territory::query()->findOrFail($siblingIds[1]),
    ];
}

/**
 * The ordered object id sequence for $scope, read directly off the query
 * builder (not the Eloquent models) — the invariant under test is the row
 * order the database returns, not anything the model layer adds to it.
 *
 * `select()` is called explicitly rather than passed to `get()`/`pluck()`:
 * `apply()` already calls `select("{$table}.*")`, and Laravel's own query
 * builder only honours a column list handed to `get($columns)` or
 * `pluck($column)` when no select was set yet
 * (`Illuminate\Database\Query\Builder::onceWithColumns()` is a no-op once
 * `$this->columns` is non-null) — silently keeping `objects.*` instead of
 * narrowing it, which this test discovered the hard way (see the falsified
 * `tier_rank`-column report in the final task summary).
 *
 * @return array<int, int>
 */
function orderedObjectIds(PlacementOrderingService $ordering, Territory|ObjectType $scope): array
{
    $query = Object_::query();
    $ordering->apply($query, $scope);

    /** @var array<int, int> */
    return $query->select(['objects.id'])->toBase()->pluck('objects.id')->all();
}

it('keeps tier precedence absolute across a territory and object-category scope sweep, and bump recency scoped to its own territory, under seeded volume', function (): void {
    $this->seed();
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\DemoVolumeSeeder', '--force' => true]);

    $objectCount = DB::table('objects')->count();
    $territoryCount = DB::table('territories')->count();

    // Twelve fixtures prove nothing about a join chain this wide — the same
    // guard the query-budget benchmark applies to its own seeded run.
    expect($objectCount)->toBeGreaterThanOrEqual(50_000)
        ->and($territoryCount)->toBeGreaterThanOrEqual(3_000);

    assignLaunchTiersToEverySeededObject();

    $countryId = DB::table('countries')->where('code', 'MD')->value('id');
    expect($countryId)->not->toBeNull();

    $countryLevelScope = territoryAtDepth($countryId, 1);
    $regionLevelScope = territoryAtDepth($countryId, 2);
    $cityLevelScope = territoryAtDepth($countryId, 3);
    $categoryScope = ObjectType::query()->orderBy('id')->firstOrFail();

    $ordering = app(PlacementOrderingService::class);

    // --- Rank dominance across a full scope sweep --------------------------
    // Four independent checks, not one: the scope only changes which bump
    // events the ordering's tiebreaker term reads, so this proves rank
    // dominance holds regardless of scope kind (territory at three different
    // depths, and an object category), not just for whichever scope a
    // narrower test happened to pick.
    $scopes = [
        'country-level territory' => $countryLevelScope,
        'region-level territory' => $regionLevelScope,
        'city-level territory' => $cityLevelScope,
        'object-category' => $categoryScope,
    ];

    foreach ($scopes as $label => $scope) {
        $query = Object_::query();
        $ordering->apply($query, $scope);

        // select() called explicitly, not passed to get(): see
        // orderedObjectIds()'s docblock for why get(['objects.id', ...])
        // alone silently keeps apply()'s own "objects.*" selection instead.
        $ranks = $query->select(['objects.id', 'pt.rank as tier_rank'])
            ->toBase()
            ->get()
            ->map(fn (object $row): int => $row->tier_rank === null ? 999 : (int) $row->tier_rank)
            ->all();

        expect($ranks)->toHaveCount($objectCount, "[{$label}] scope did not return every seeded object.");

        for ($i = 1, $count = count($ranks); $i < $count; $i++) {
            expect($ranks[$i])->toBeGreaterThanOrEqual(
                $ranks[$i - 1],
                "[{$label}] scope: rank regressed from {$ranks[$i - 1]} to {$ranks[$i]} at ordered position {$i} — a lower-tier object was placed above a higher-tier one."
            );
        }
    }

    // --- Bump recency never leaks across a scope boundary -------------------
    [$cityA, $cityB] = siblingCityLevelTerritories($countryId);

    $objectToBump = Object_::query()->orderBy('id')->firstOrFail();

    $preBumpCityAOrder = orderedObjectIds($ordering, $cityA);
    $preBumpCityBOrder = orderedObjectIds($ordering, $cityB);

    $actor = User::factory()->create();
    $event = app(BumpService::class)->bump($objectToBump, $cityA, $actor, 'administrator', 'seeded-volume isolation check');

    $postBumpCityAOrder = orderedObjectIds($ordering, $cityA);
    $postBumpCityBOrder = orderedObjectIds($ordering, $cityB);

    expect($event->scope_type)->toBe(Territory::class)
        ->and($event->scope_id)->toBe($cityA->id)
        // The bump must actually move the object to the front of its own
        // tier for the scope it was recorded against — asserting only that
        // the sibling scope is untouched would pass vacuously if the bump
        // term were silently dead everywhere.
        ->and($postBumpCityAOrder[0])->toBe($objectToBump->id)
        ->and($postBumpCityAOrder)->not->toBe($preBumpCityAOrder)
        // The sibling territory reads no bump event for its own scope_id
        // either before or after — byte-identical ordering either way.
        ->and($postBumpCityBOrder)->toBe($preBumpCityBOrder);

    // --- Ordering query stays inside the portal's own per-request budget ----
    DB::flushQueryLog();
    DB::enableQueryLog();

    $page = Object_::query()->tap(fn ($q) => $ordering->apply($q, $cityLevelScope))->paginate(20);

    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();
    DB::flushQueryLog();

    expect($page->items())->not->toBeEmpty()
        ->and($queryCount)->toBeLessThanOrEqual(30, "The ordering query issued {$queryCount} queries against a 30-query budget.");

    // Findings are recorded as numbers, not a pass/fail claim — read from
    // the actual run rather than hand-typed after the fact.
    fwrite(STDERR, "\n".json_encode([
        'seeded' => ['objects' => $objectCount, 'territories' => $territoryCount],
        'ordering_query_budget' => $queryCount,
    ], JSON_PRETTY_PRINT)."\n");
})->group('slow');
