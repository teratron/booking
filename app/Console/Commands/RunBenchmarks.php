<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Measures the portal's two hottest paths against seeded volume — the
 * catalog ranking query and territory subtree expansion — so the
 * performance budgets are numbers this command checks, not numbers someone
 * eventually notices were never true. No public page exists yet to measure
 * end-to-end, so this benchmarks the queries underneath where one will
 * render; re-run whenever either query or the schema it reads changes.
 */
final class RunBenchmarks extends Command
{
    protected $signature = 'bench:run';

    protected $description = 'Benchmark the catalog ranking query and territory subtree expansion against seeded volume';

    private const int MIN_OBJECTS_FOR_REALISTIC_VOLUME = 50_000;

    private const int MIN_TERRITORIES_FOR_REALISTIC_VOLUME = 3_000;

    private const float CATALOG_RANKING_BUDGET_MS = 400.0;

    private const int MAX_QUERIES_PER_REQUEST = 30;

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

        $rankingPassed = $this->benchmarkCatalogRanking();
        $subtreePassed = $this->benchmarkTerritorySubtreeExpansion();
        $failed = ! $rankingPassed || ! $subtreePassed;

        $this->newLine();
        $this->line('Not yet measurable — no public page or cache layer exists to measure end-to-end (Phase 5 closes these):');
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
}
