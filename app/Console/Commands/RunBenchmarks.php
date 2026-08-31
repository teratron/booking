<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Object_;
use App\Models\Territory;
use App\Services\Catalog\CatalogQueryService;
use App\Services\Catalog\ObjectProfilePresenter;
use App\Support\Catalog\CatalogSearchCriteria;
use Closure;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Measures the portal's hottest paths against seeded volume. Two scenarios:
 *
 * - `core` (default, unchanged since this command's own introduction): the
 *   catalog ranking query and territory subtree expansion, each a proxy
 *   query built directly on the underlying tables rather than through a
 *   service — written before either public page existed to call one.
 * - `load` (`--scenario=load`): a pre-launch load test against the public
 *   pages that now exist, driven through the same retrieval services those
 *   pages call — {@see CatalogQueryService::search()} and
 *   {@see ObjectProfilePresenter::present()} — never a page-specific query
 *   of its own. Reports p50/p95 wall-clock and query counts per surface,
 *   optionally as a committed JSON artefact via `--report=`.
 *
 * Every figure this command reports is measured and printed, never
 * hard-asserted against a millisecond budget — the container's own bind
 * mount from a Windows host is not a reliable benchmark machine, confirmed
 * repeatedly across this project's own history. The one figure that IS
 * deterministic and gets asserted elsewhere is the ≤30-query ceiling, via
 * `tests/Feature/Public/PublicPerformanceBudgetTest.php`.
 */
final class RunBenchmarks extends Command
{
    protected $signature = 'bench:run {--scenario=core : "core" (catalog ranking + territory subtree) or "load" (pre-launch page load test)} {--report= : Path to write a JSON report (load scenario only)}';

    protected $description = 'Benchmark the catalog ranking query and territory subtree expansion, or run a pre-launch load test, against seeded volume';

    private const int MIN_OBJECTS_FOR_REALISTIC_VOLUME = 50_000;

    private const int MIN_TERRITORIES_FOR_REALISTIC_VOLUME = 3_000;

    private const float CATALOG_RANKING_BUDGET_MS = 400.0;

    private const int MAX_QUERIES_PER_REQUEST = 30;

    /** Samples collected per surface before computing p50/p95 — enough for a stable percentile without turning this into a multi-minute run. */
    private const int LOAD_SAMPLES_PER_SURFACE = 15;

    private const int LOAD_SEARCH_SAMPLES = 30;

    public function __construct(
        private readonly CatalogQueryService $catalog,
        private readonly ObjectProfilePresenter $objectPresenter,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $objectCount = DB::table('objects')->count();
        $territoryCount = DB::table('territories')->count();

        if ($objectCount < self::MIN_OBJECTS_FOR_REALISTIC_VOLUME || $territoryCount < self::MIN_TERRITORIES_FOR_REALISTIC_VOLUME) {
            $this->error(sprintf(
                'Seeded volume too low to benchmark meaningfully (%d objects, %d territories). Run `php artisan db:seed --class=DemoVolumeSeeder` first.',
                $objectCount,
                $territoryCount
            ));

            return self::FAILURE;
        }

        $scenario = (string) $this->option('scenario');

        return match ($scenario) {
            'core' => $this->runCoreScenario(),
            'load' => $this->runLoadScenario($objectCount, $territoryCount),
            default => $this->failUnknownScenario($scenario),
        };
    }

    private function failUnknownScenario(string $scenario): int
    {
        $this->error(sprintf('Unknown --scenario=%s. Expected "core" or "load".', $scenario));

        return self::FAILURE;
    }

    private function runCoreScenario(): int
    {
        $rankingPassed = $this->benchmarkCatalogRanking();
        $subtreePassed = $this->benchmarkTerritorySubtreeExpansion();
        $failed = ! $rankingPassed || ! $subtreePassed;

        $this->newLine();
        $this->line('Not yet measurable — no public page or cache layer exists yet to measure end-to-end:');
        $this->line('  - Catalog page, cache hit (< 100ms TTFB budget)');
        $this->line('  - Object page, cache miss (< 300ms budget)');
        $this->line('  - Search, p95 (< 300ms budget — no search endpoint yet)');

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Proxy for the "catalog page, cache miss" budget: the scoped,
     * tier-ordered listing query a catalog page issues, without the page
     * itself. Left-joins placement data so the query is genuinely
     * tier-first — `[TZ]` §25.2 forbids "improving" this into
     * relevance-first ordering — even though `DemoVolumeSeeder` does not
     * yet assign every object a placement, so every candidate row falls
     * into the same untiered bucket today; row volume, join shape, and
     * index usage are still realistic at 52,800 candidate objects.
     */
    private function benchmarkCatalogRanking(): bool
    {
        /** @var object{country_id: int, territory_id: int} $scope */
        $scope = DB::table('objects')
            ->select('country_id', 'territory_id')
            ->where('status', 'published')
            ->first();

        DB::enableQueryLog();
        $start = hrtime(true);

        $results = DB::table('objects as o')
            ->leftJoin('object_placements as op', 'op.object_id', '=', 'o.id')
            ->leftJoin('placement_packages as pp', 'pp.id', '=', 'op.placement_package_id')
            ->leftJoin('placement_tiers as pt', 'pt.id', '=', 'pp.placement_tier_id')
            ->where('o.country_id', $scope->country_id)
            ->where('o.territory_id', $scope->territory_id)
            ->where('o.status', 'published')
            ->whereNull('o.deleted_at')
            ->orderByRaw('coalesce(pt.rank, 999) asc')
            ->orderBy('o.id', 'desc')
            ->limit(20)
            ->get(['o.id']);

        $elapsedMs = (hrtime(true) - $start) / 1_000_000;
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $this->report('Catalog ranking query', $elapsedMs, self::CATALOG_RANKING_BUDGET_MS, $queryCount, $results->count());
    }

    /**
     * The recursive subtree expansion every territory page and every
     * catalog view scoped below the country level relies on, walking
     * `territories.parent_id` — the same query `ScopeAuthorizer` uses to
     * resolve a territory-scoped grant.
     */
    private function benchmarkTerritorySubtreeExpansion(): bool
    {
        /** @var object{id: int} $root */
        $root = DB::table('territories')->whereNull('parent_id')->first()
            ?? DB::table('territories')->orderBy('id')->first();

        DB::enableQueryLog();
        $start = hrtime(true);

        $descendants = DB::select(<<<'SQL'
            with recursive subtree as (
                select id from territories where id = ?
                union all
                select t.id from territories t inner join subtree s on t.parent_id = s.id
            )
            select id from subtree
            SQL, [$root->id]);

        $elapsedMs = (hrtime(true) - $start) / 1_000_000;
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // No budget row names this query specifically — measured and
        // reported against the query-count ceiling only, per this task's
        // own Verify line.
        return $this->report('Territory subtree expansion', $elapsedMs, null, $queryCount, count($descendants));
    }

    private function report(string $label, float $elapsedMs, ?float $budgetMs, int $queryCount, int $rowCount): bool
    {
        $passed = $queryCount <= self::MAX_QUERIES_PER_REQUEST && ($budgetMs === null || $elapsedMs <= $budgetMs);

        $budgetLabel = $budgetMs !== null ? sprintf('%.0fms budget', $budgetMs) : 'no ms budget';
        $status = $passed ? '<info>PASS</info>' : '<error>FAIL</error>';

        $this->line(sprintf(
            '%s — %s: %.2fms (%s), %d queries (%d max), %d rows',
            $status,
            $label,
            $elapsedMs,
            $budgetLabel,
            $queryCount,
            self::MAX_QUERIES_PER_REQUEST,
            $rowCount
        ));

        return $passed;
    }

    /**
     * Pre-launch load test (`[TZ]` §18/§94's own performance requirement,
     * run before launch rather than after): drives the catalog, territory,
     * object, and name-search surfaces through the exact retrieval services
     * their public pages call, at `DemoVolumeSeeder`'s 50,000+ object
     * volume, and reports p50/p95 wall-clock plus query counts per surface.
     *
     * Deliberately measures the retrieval/cache layer directly rather than
     * a full HTTP round trip through routing, middleware, and Blade
     * rendering: this container's own codebase is bind-mounted from the
     * Windows host it runs on, and a full-request measurement was tried
     * first — framework bootstrap alone (provider registration, unrelated
     * to any of this project's own code) was observed to cost anywhere from
     * roughly one second to over a hundred, entirely dependent on the host's
     * own I/O state at that moment, which would swamp any query-layer
     * signal this benchmark exists to surface. The retrieval services
     * measured here are the same ones `PublicPerformanceBudgetTest` drives
     * through a real HTTP request in a single warmed-up test process; this
     * command adds the percentile-over-many-samples view that a single
     * miss/hit pair cannot give, at the volume that test's own few hundred
     * seeded objects cannot represent.
     */
    private function runLoadScenario(int $objectCount, int $territoryCount): int
    {
        $this->line(sprintf('Load scenario — %d objects, %d territories seeded.', $objectCount, $territoryCount));
        $this->newLine();

        $catalog = $this->sampleCatalogSurface();
        $territory = $this->sampleTerritorySurface();
        $object = $this->sampleObjectSurface();
        $search = $this->sampleSearchSurface();

        $report = [
            'generated_at' => now()->toIso8601String(),
            'seeded' => ['objects' => $objectCount, 'territories' => $territoryCount],
            'methodology' => 'Retrieval/cache layer measured directly through the same services each public page calls '
                .'(CatalogQueryService::search(), ObjectProfilePresenter::present()) — not a full HTTP round trip. '
                .'Framework bootstrap cost on this host varies too widely (roughly 1s to over 100s, observed) to let a '
                .'full-request measurement isolate query-layer signal. Wall-clock ms is measured and reported, never '
                .'hard-asserted; the ≤30-query ceiling is asserted separately by PublicPerformanceBudgetTest.',
            'surfaces' => [
                'catalog' => array_merge(['budget_ms' => ['miss' => 400, 'hit' => 100]], $catalog),
                'territory' => array_merge(['budget_ms' => ['miss' => 400, 'hit' => 100]], $territory),
                'object' => array_merge(['budget_ms' => ['miss' => 300, 'hit' => null]], $object),
                'search' => array_merge(['budget_ms' => ['p95' => 300], 'escalate_to_typesense' => $search['p95_ms'] > 300.0], $search),
            ],
        ];

        $this->printLoadSummary($report);

        $reportPath = $this->option('report');

        if (is_string($reportPath) && $reportPath !== '') {
            $this->writeReport($reportPath, $report);
        }

        return self::SUCCESS;
    }

    /**
     * Proxies the catalog page's own per-block query: one territory + type
     * scoped page through {@see CatalogQueryService::search()}, the same
     * contract the catalog page and every territory block call. `$n`
     * independent (territory, type) pairs give the miss distribution;
     * each is immediately repeated once, unchanged, for the hit sample.
     *
     * @return array{miss: array<string, mixed>, hit: array<string, mixed>}
     */
    private function sampleCatalogSurface(): array
    {
        $pairs = $this->randomTerritoryTypePairs(self::LOAD_SAMPLES_PER_SURFACE);

        $missMs = [];
        $missQueries = [];
        $hitMs = [];
        $hitQueries = [];

        foreach ($pairs as [$territoryId, $typeId]) {
            Cache::flush();

            $criteria = new CatalogSearchCriteria(
                territory: Territory::query()->find($territoryId),
                objectTypeId: $typeId,
                perPage: 6,
            );

            $miss = $this->timeAndCount(fn () => $this->catalog->search($criteria));
            $missMs[] = $miss['elapsedMs'];
            $missQueries[] = $miss['queryCount'];

            $hit = $this->timeAndCount(fn () => $this->catalog->search($criteria));
            $hitMs[] = $hit['elapsedMs'];
            $hitQueries[] = $hit['queryCount'];
        }

        return [
            'miss' => $this->percentiles($missMs, $missQueries),
            'hit' => $this->percentiles($hitMs, $hitQueries),
        ];
    }

    /**
     * Proxies a territory page's own two retrieval costs: the sidebar
     * (news/promotions/children, cached as a unit — the same shape
     * `TerritoryPageController::show()` caches) and the catalog blocks (one
     * {@see CatalogQueryService::search()} call per active object type,
     * exactly as `TerritoryPageController::catalogBlocks()` issues them).
     *
     * @return array{miss: array<string, mixed>, hit: array<string, mixed>}
     */
    private function sampleTerritorySurface(): array
    {
        $territoryIds = $this->randomLeafTerritoryIds(self::LOAD_SAMPLES_PER_SURFACE);
        $typeIds = $this->activeObjectTypeIds();

        $missMs = [];
        $missQueries = [];
        $hitMs = [];
        $hitQueries = [];

        foreach ($territoryIds as $territoryId) {
            // Resolved once, outside the timed/counted section — a real
            // territory page already has `$territory` in hand (routing
            // resolved it) before TerritoryPageController::show() runs any
            // of the queries measured below.
            $territory = Territory::query()->find($territoryId);

            Cache::flush();

            $miss = $this->timeAndCount(fn () => $this->renderTerritorySurface($territoryId, $territory, $typeIds));
            $missMs[] = $miss['elapsedMs'];
            $missQueries[] = $miss['queryCount'];

            $hit = $this->timeAndCount(fn () => $this->renderTerritorySurface($territoryId, $territory, $typeIds));
            $hitMs[] = $hit['elapsedMs'];
            $hitQueries[] = $hit['queryCount'];
        }

        return [
            'miss' => $this->percentiles($missMs, $missQueries),
            'hit' => $this->percentiles($hitMs, $hitQueries),
        ];
    }

    /** @param  list<int>  $typeIds */
    private function renderTerritorySurface(int $territoryId, ?Territory $territory, array $typeIds): void
    {
        Cache::remember(
            sprintf('bench:territory:sidebar:%d', $territoryId),
            300,
            fn (): array => [
                'newsItems' => DB::table('news_items')->where('territory_id', $territoryId)->where('status', 'published')->limit(6)->get(),
                'promotions' => DB::table('promotions')->where('territory_id', $territoryId)->where('status', 'published')->limit(6)->get(),
                'childTerritories' => DB::table('territories')->where('parent_id', $territoryId)->where('is_active', true)->get(),
            ]
        );

        foreach ($typeIds as $typeId) {
            $criteria = new CatalogSearchCriteria(territory: $territory, objectTypeId: $typeId, perPage: 6);
            $this->catalog->search($criteria);
        }
    }

    /**
     * Proxies the object page's own cache boundary: {@see
     * ObjectProfilePresenter::present()}, wrapped in the identical
     * `Cache::remember()` key shape `ObjectPageController::show()` uses.
     *
     * @return array{miss: array<string, mixed>, hit: array<string, mixed>}
     */
    private function sampleObjectSurface(): array
    {
        $objectIds = DB::table('objects')->where('status', 'published')->inRandomOrder()->limit(self::LOAD_SAMPLES_PER_SURFACE)->pluck('id');

        $missMs = [];
        $missQueries = [];
        $hitMs = [];
        $hitQueries = [];

        foreach ($objectIds as $objectId) {
            Cache::flush();

            $object = Object_::query()->with(['translations', 'territory.translations', 'territory.country', 'objectType'])->find($objectId);

            if (! $object instanceof Object_) {
                continue;
            }

            $miss = $this->timeAndCount(fn () => Cache::remember(
                sprintf('bench:object:profile:%d', $objectId),
                300,
                fn () => $this->objectPresenter->present($object)
            ));
            $missMs[] = $miss['elapsedMs'];
            $missQueries[] = $miss['queryCount'];

            $hit = $this->timeAndCount(fn () => Cache::remember(
                sprintf('bench:object:profile:%d', $objectId),
                300,
                fn () => $this->objectPresenter->present($object)
            ));
            $hitMs[] = $hit['elapsedMs'];
            $hitQueries[] = $hit['queryCount'];
        }

        return [
            'miss' => $this->percentiles($missMs, $missQueries),
            'hit' => $this->percentiles($hitMs, $hitQueries),
        ];
    }

    /**
     * The catalog's own name filter (`ilike '%term%'` against translated
     * names — this project's search surface until it escalates to
     * Typesense) driven with `$n` distinct terms so every
     * sample is a genuine cache miss, the realistic case for free-text
     * search input. p95 is the headline figure this scenario exists to
     * produce: over 300ms is the specification's own stated trigger to
     * escalate to Typesense.
     *
     * @return array<string, mixed>
     */
    private function sampleSearchSurface(): array
    {
        // A short substring from a real seeded name, not a whole word — every
        // seeded name shares the trailing "(en)" locale marker, and picking
        // that token as a search term would give every sample perfect
        // selectivity, understating the worst-case ILIKE cost a genuine
        // partial-name query pays.
        $terms = DB::table('object_translations')
            ->where('locale', 'en')
            ->inRandomOrder()
            ->limit(self::LOAD_SEARCH_SAMPLES)
            ->pluck('name')
            ->map(function (string $name): string {
                $core = trim(Str::before($name, ' ('));
                $start = random_int(0, max(0, mb_strlen($core) - 5));

                return mb_substr($core, $start, 5);
            })
            ->all();

        $ms = [];
        $queries = [];

        foreach ($terms as $term) {
            Cache::flush();

            $criteria = new CatalogSearchCriteria(name: (string) $term, perPage: 20);
            $sample = $this->timeAndCount(fn () => $this->catalog->search($criteria));
            $ms[] = $sample['elapsedMs'];
            $queries[] = $sample['queryCount'];
        }

        return $this->percentiles($ms, $queries);
    }

    /** @return array<int, array{0: int, 1: int}> */
    private function randomTerritoryTypePairs(int $n): array
    {
        $territoryIds = $this->randomLeafTerritoryIds($n);
        $typeIds = $this->activeObjectTypeIds();

        return array_map(
            fn (int $territoryId, int $index): array => [$territoryId, $typeIds[$index % count($typeIds)]],
            $territoryIds,
            array_keys($territoryIds),
        );
    }

    /** @return list<int> */
    private function randomLeafTerritoryIds(int $n): array
    {
        // Postgres refuses `SELECT DISTINCT ... ORDER BY RANDOM()` (the
        // random expression isn't in the select list); GROUP BY has no such
        // restriction and de-duplicates identically here since there is no
        // aggregate to disagree about.
        $ids = DB::table('objects')
            ->select('territory_id')
            ->groupBy('territory_id')
            ->inRandomOrder()
            ->limit($n)
            ->pluck('territory_id')
            ->all();

        return array_values(array_map(intval(...), $ids));
    }

    /** @return list<int> */
    private function activeObjectTypeIds(): array
    {
        $ids = DB::table('object_types')->where('is_active', true)->pluck('id')->all();

        return array_values(array_map(intval(...), $ids));
    }

    /**
     * @return array{elapsedMs: float, queryCount: int}
     */
    private function timeAndCount(Closure $callback): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $start = hrtime(true);

        $callback();

        $elapsedMs = (hrtime(true) - $start) / 1_000_000;
        $queryCount = count(DB::getQueryLog());

        DB::disableQueryLog();
        DB::flushQueryLog();

        return ['elapsedMs' => $elapsedMs, 'queryCount' => $queryCount];
    }

    /**
     * @param  list<float>  $msSamples
     * @param  list<int>  $querySamples
     * @return array{p50_ms: float, p95_ms: float, min_ms: float, max_ms: float, query_count_p50: int, query_count_max: int, samples: int}
     */
    private function percentiles(array $msSamples, array $querySamples): array
    {
        if ($msSamples === []) {
            return ['p50_ms' => 0.0, 'p95_ms' => 0.0, 'min_ms' => 0.0, 'max_ms' => 0.0, 'query_count_p50' => 0, 'query_count_max' => 0, 'samples' => 0];
        }

        $sortedMs = $msSamples;
        sort($sortedMs);
        $sortedQueries = $querySamples;
        sort($sortedQueries);

        return [
            'p50_ms' => $this->percentile($sortedMs, 0.50),
            'p95_ms' => $this->percentile($sortedMs, 0.95),
            'min_ms' => round($sortedMs[0], 2),
            'max_ms' => round($sortedMs[count($sortedMs) - 1], 2),
            'query_count_p50' => (int) round($this->percentile($sortedQueries, 0.50)),
            'query_count_max' => $sortedQueries[count($sortedQueries) - 1],
            'samples' => count($msSamples),
        ];
    }

    /** @param  list<float|int>  $sorted  already sorted ascending */
    private function percentile(array $sorted, float $p): float
    {
        $n = count($sorted);
        $index = max(0, min($n - 1, (int) ceil($p * $n) - 1));

        return round((float) $sorted[$index], 2);
    }

    /** @param  array<string, mixed>  $report */
    private function printLoadSummary(array $report): void
    {
        $this->newLine();

        foreach ($report['surfaces'] as $surface => $data) {
            if ($surface === 'search') {
                $overBudget = $data['p95_ms'] > $data['budget_ms']['p95'];
                $status = $overBudget ? '<comment>OVER BUDGET</comment>' : '<info>within budget</info>';
                $this->line(sprintf(
                    'search — p50 %.2fms / p95 %.2fms (%dms budget, %s), %d queries max, %d samples',
                    $data['p50_ms'],
                    $data['p95_ms'],
                    $data['budget_ms']['p95'],
                    $status,
                    $data['query_count_max'],
                    $data['samples'],
                ));

                if ($overBudget) {
                    $this->warn('  search p95 exceeds the 300ms Typesense-escalation trigger.');
                }

                continue;
            }

            foreach (['miss', 'hit'] as $state) {
                $budget = $data['budget_ms'][$state] ?? null;
                $d = $data[$state];
                $budgetLabel = $budget !== null ? sprintf('%dms budget', $budget) : 'no ms budget';
                $overBudget = $budget !== null && $d['p95_ms'] > $budget;
                $status = $overBudget ? '<comment>OVER BUDGET</comment>' : '<info>within budget</info>';

                $this->line(sprintf(
                    '%s (%s) — p50 %.2fms / p95 %.2fms (%s, %s), %d queries max, %d samples',
                    $surface,
                    $state,
                    $d['p50_ms'],
                    $d['p95_ms'],
                    $budgetLabel,
                    $status,
                    $d['query_count_max'],
                    $d['samples'],
                ));
            }
        }
    }

    /** @param  array<string, mixed>  $report */
    private function writeReport(string $path, array $report): void
    {
        $fullPath = str_starts_with($path, '/') || preg_match('/^[A-Za-z]:\\\\/', $path) === 1
            ? $path
            : base_path($path);

        $directory = dirname($fullPath);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($fullPath, json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n");

        $this->newLine();
        $this->info("Report written to {$fullPath}");
    }
}
