<?php

declare(strict_types=1);

namespace App\Services\Placement;

use App\Models\ObjectType;
use App\Models\Territory;
use App\Services\Settings\SettingsRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * The catalog's `ORDER BY` contract, built once here and consumed by every
 * caller that needs a tier-first listing — the public catalog page (a later
 * phase), the back office's own reports, and this phase's own volume test.
 * Rank dominates every later term; nothing below it can promote a
 * lower-ranked object above a higher-ranked one.
 *
 * `objects.rating` does not exist yet (a maintained aggregate the
 * specification's own ordering contract names, deferred per this project's
 * documented constraint) and `rotation_seed` is the specification's own
 * optional, session-stable tiebreaker — both are consciously absent rather
 * than faked with a placeholder value, which would assert data the portal
 * does not have.
 */
final class PlacementOrderingService
{
    public function __construct(
        private readonly SettingsRepository $settings,
    ) {}

    /**
     * Applies the ordering contract to $query in place and returns it for
     * chaining. $scope is the territory or object type the page currently
     * renders — bump recency is read for that scope only, per the
     * specification's own scope-locality invariant; passing no scope reads
     * no bump term (every object ties on it, falling through to the next).
     *
     * @param  Builder<covariant Model>  $query
     * @return Builder<covariant Model>
     */
    public function apply(Builder $query, Territory|ObjectType|null $scope = null): Builder
    {
        // The within-tier tiebreaker is the one part of this contract an
        // administrator may change (`presentation.within_tier_order`); only
        // `recent_bump` — the specification's own described chain — exists
        // today, so an unrecognised value falls back to it rather than
        // silently changing behaviour on a typo.
        $withinTierOrder = (string) $this->settings->get('presentation.within_tier_order');

        $model = $query->getModel();
        $table = $model->getTable();

        $query
            ->leftJoin('object_placements as op', 'op.object_id', '=', "{$table}.id")
            ->leftJoin('placement_packages as pp', 'pp.id', '=', 'op.placement_package_id')
            ->leftJoin('placement_tiers as pt', 'pt.id', '=', 'pp.placement_tier_id')
            ->leftJoinSub($this->latestBumpSubquery($scope), 'bump', function ($join) use ($table): void {
                $join->on('bump.object_id', '=', "{$table}.id");
            })
            ->leftJoin('object_promotions as promo', function ($join) use ($table): void {
                $join->on('promo.object_id', '=', "{$table}.id")
                    ->where('promo.starts_at', '<=', now()->toDateString())
                    ->where('promo.ends_at', '>=', now()->toDateString());
            })
            ->select("{$table}.*")
            ->orderByRaw('coalesce(pt.rank, 999) asc')
            ->orderByRaw('op.pinned_position asc nulls last');

        if ($withinTierOrder === 'recent_bump') {
            $query->orderByRaw('bump.occurred_at desc nulls last');
        }

        $query
            ->orderByRaw('coalesce(promo.weight, 0) desc')
            ->orderByRaw('coalesce(op.internal_priority, 0) desc')
            ->orderBy("{$table}.created_at", 'desc');

        return $query;
    }

    /**
     * One row per object: the most recent bump recorded for the given
     * scope. Reading "for the scope being viewed" rather than a single
     * global timestamp is what makes a bump on one territory page leave a
     * sibling territory's ordering untouched.
     */
    private function latestBumpSubquery(Territory|ObjectType|null $scope): QueryBuilder
    {
        $query = DB::table('bump_events')
            ->select('object_id')
            ->selectRaw('max(occurred_at) as occurred_at')
            ->groupBy('object_id');

        if ($scope === null) {
            // No scope resolves to no bump match for any object — every row
            // ties on this term and ordering falls through to the next one,
            // which is the correct behaviour for a caller that has not
            // resolved a rendering scope (e.g. a portal-wide report).
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->where('scope_type', $scope::class)
            ->where('scope_id', $scope->id);
    }
}
