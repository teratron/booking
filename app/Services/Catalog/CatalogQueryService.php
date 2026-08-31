<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Models\Object_;
use App\Models\ObjectType;
use App\Models\PlacementTier;
use App\Models\Price;
use App\Models\Room;
use App\Models\Territory;
use App\Services\Api\ApiTokenService;
use App\Services\Authorization\ResourceQueryScoper;
use App\Services\Authorization\ScopeConstraint;
use App\Services\Placement\PlacementOrderingService;
use App\Support\Analytics\StatEventKind;
use App\Support\Catalog\CatalogSearchCriteria;
use App\Support\Catalog\MapBounds;
use App\Support\Catalog\MapCluster;
use App\Support\Catalog\MapPin;
use App\Support\Catalog\MapPinsResult;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The single retrieval contract every public listing surface calls — home,
 * territory pages, the catalog/search page, and an object's own nearby/
 * similar block. Scope resolution, filtering, and pagination live here;
 * tier-first ordering does not — that is
 * {@see PlacementOrderingService::apply()}, built in an earlier phase
 * specifically for this call site, and this service delegates to it rather
 * than carrying a second `ORDER BY` implementation.
 *
 * Every result is public-visible by construction: `Object_`'s own
 * `ModerationScope` (approved content only) applies to every unqualified
 * query, and this service additionally requires `status = 'published'` —
 * the one publication state `ModerationScope` does not itself express.
 */
final class CatalogQueryService
{
    /**
     * Every listing surface in the phase reads through this one cache —
     * catalog search, each territory-page block, the home page's rails, and
     * an object profile's nearby/similar blocks — so a single short TTL is
     * the portal's entire "catalog page, cache hit" budget in practice. No
     * write-path invalidation: a placement change or a new listing becomes
     * visible within one TTL window, which this catalog's own domain
     * (browse, not a live order book) tolerates without needing an
     * event-driven invalidation pipeline.
     */
    private const int RESULT_CACHE_TTL_SECONDS = 300;

    /**
     * Keys {@see ObjectCardPresenter} reads before falling back to its own
     * live per-object query — set once per page here instead of once per
     * card there. Public because the presenter, a sibling service, is the
     * one caller these are addressed to.
     */
    public const string CARD_REVIEW_AVERAGE_KEY = 'catalog_card_review_average';

    public const string CARD_REVIEW_COUNT_KEY = 'catalog_card_review_count';

    public const string CARD_VIEW_COUNT_KEY = 'catalog_card_view_count';

    public const string CARD_PRICE_AMOUNT_KEY = 'catalog_card_price_amount';

    public const string CARD_PRICE_CURRENCY_KEY = 'catalog_card_price_currency';

    /**
     * The tier badge/border a card renders — batched the same way as the
     * three aggregates above, via a plain join query rather than
     * Eloquent's own `placement.package.tier` relation chain: eager-loading
     * that relation here, whether via `with()` at query-build time or
     * `Collection::load()` after, reproducibly breaks and drastically slows
     * a query already going through {@see PlacementOrderingService::apply()}'s
     * own joins against the identical `object_placements`/
     * `placement_packages`/`placement_tiers` tables (root cause not
     * isolated further within this change's own scope) — a plain,
     * relation-free query sidesteps whatever that interaction is.
     */
    public const string CARD_TIER_BADGE_TEXT_KEY = 'catalog_card_tier_badge_text';

    public const string CARD_TIER_BADGE_COLOUR_KEY = 'catalog_card_tier_badge_colour';

    public const string CARD_TIER_BORDER_COLOUR_KEY = 'catalog_card_tier_border_colour';

    /**
     * Below this zoom, {@see self::pins()} returns grid-cell {@see MapCluster}
     * aggregates instead of individual markers — a country-wide viewport at
     * launch volume serialises tens of thousands of points otherwise.
     * Chosen to sit strictly between this project's own country-wide map
     * zooms (the home page's `6`, the catalog page's default `5`) and its
     * city/object zooms (the territory page's `11`, the object page's
     * `15`), so every existing map placement already lands cleanly on one
     * side of the threshold.
     */
    public const int CLUSTER_THRESHOLD_ZOOM = 9;

    /**
     * The most individual pins a single response ever carries, regardless
     * of how many objects actually match — a country-wide viewport at or
     * past the threshold zoom (a visitor who scrolled out again after
     * zooming in) can still match far more than a map usefully renders
     * pin-by-pin. {@see MapPinsResult::$truncated} tells the caller when the
     * true match count exceeded this cap, so the client can prompt the
     * visitor to zoom in rather than silently dropping the rest.
     */
    public const int MAX_PINS_PER_RESPONSE = 500;

    /**
     * The whole globe's own longitude span — halved on every step up in
     * zoom, the same doubling-resolution-per-level convention Web Mercator
     * tiles use, so zoom `0` collapses everything into one or two cells and
     * each further step meaningfully coarsens or refines the grid. Deliberately
     * generous rather than pixel-accurate tile math: even at the finest
     * pre-threshold zoom (one below {@see self::CLUSTER_THRESHOLD_ZOOM}),
     * a cell must still span multiple neighbouring territories — a country-
     * wide viewport that resolves down to near-territory-sized cells
     * defeats clustering's entire purpose of keeping the response small.
     */
    private const float CLUSTER_BASE_CELL_DEGREES = 360.0;

    /**
     * Every relation {@see ObjectCardPresenter} and {@see ObjectProfilePresenter}
     * read via their own per-object `loadMissing()` — listed here so Eloquent
     * batches one query per relation for the whole page instead of one per
     * object. `loadMissing()` downstream then finds every one of these
     * already loaded and does nothing; nothing downstream needs to change
     * to benefit. Deliberately excludes `placement.package.tier` — see
     * {@see self::CARD_TIER_BADGE_TEXT_KEY}'s own docblock.
     *
     * @var list<string>
     */
    private const array CARD_RELATIONS = [
        'translations', 'objectType', 'territory.translations', 'amenities.translations',
        'contactChannels.contactChannelType.translations', 'media',
    ];

    public function __construct(
        private readonly PlacementOrderingService $ordering,
        private readonly ResourceQueryScoper $scoper,
    ) {}

    /**
     * $constraint narrows the result to what a caller's own authorization
     * additionally restricts it to — the public API's token scope, resolved
     * by {@see ApiTokenService::scopeConstraint()} — on top
     * of whatever `$criteria` already filters by. Every public page calls
     * this with `$constraint` left `null` (unrestricted): the portal's own
     * pages carry no such restriction, only an issued token does.
     *
     * @return LengthAwarePaginator<int, Object_>
     */
    public function search(CatalogSearchCriteria $criteria, ?ScopeConstraint $constraint = null): LengthAwarePaginator
    {
        $tags = ['catalog'];

        if ($criteria->territory instanceof Territory) {
            $tags[] = "territory:{$criteria->territory->id}";
        }

        return Cache::tags($tags)->remember(
            $this->cacheKey($criteria, $constraint),
            self::RESULT_CACHE_TTL_SECONDS,
            function () use ($criteria, $constraint): LengthAwarePaginator {
                $query = $this->buildQuery($criteria, $constraint);

                $results = $query->paginate($criteria->perPage, ['*'], 'page', $criteria->page);

                $this->attachCardAggregates($results->getCollection());

                return $results;
            }
        );
    }

    /**
     * @return Builder<Object_>
     */
    private function buildQuery(CatalogSearchCriteria $criteria, ?ScopeConstraint $constraint = null): Builder
    {
        $query = Object_::query()->where('status', 'published')->with(self::CARD_RELATIONS);

        $scope = $this->applyFilters($query, $criteria);

        if ($constraint instanceof ScopeConstraint) {
            $this->scoper->applyConstraint($query, $constraint, 'objects.country_id', null, 'objects.object_type_id');
        }

        $this->ordering->apply($query, $scope);

        return $query;
    }

    /**
     * Batches the three aggregates every card reads (review average/count,
     * lifetime page-view count, cheapest price) into three queries for the
     * whole page, rather than the three-per-card cost a naive per-object
     * read would pay. Prices attach either directly to the object or to one
     * of its rooms — the same either/or {@see applyPriceFilter()} already
     * tests — resolved here in PHP after one query for each side, since
     * "cheapest row, but I also need its currency" has no single built-in
     * Eloquent aggregate helper that returns more than one column.
     *
     * @param  Collection<int, Object_>  $objects
     */
    private function attachCardAggregates(Collection $objects): void
    {
        $ids = $objects->pluck('id')->unique()->all();

        if ($ids === []) {
            return;
        }

        $reviewsByObjectId = DB::table('reviews')
            ->select('object_id', DB::raw('avg(rating) as average'), DB::raw('count(*) as total'))
            ->whereIn('object_id', $ids)
            ->where('status', 'published')
            ->whereNull('deleted_at')
            ->groupBy('object_id')
            ->get()
            ->keyBy(fn (object $row): int => (int) $row->object_id);

        $viewCountsByObjectId = DB::table('stat_dailies')
            ->select('subject_id', DB::raw('sum(count) as total'))
            ->where('subject_type', Object_::class)
            ->whereIn('subject_id', $ids)
            ->where('kind', StatEventKind::ObjectPageView->value)
            ->groupBy('subject_id')
            ->get()
            ->keyBy(fn (object $row): int => (int) $row->subject_id);

        $objectIdByRoomId = DB::table('rooms')->whereIn('object_id', $ids)->pluck('object_id', 'id');

        $cheapestPriceByObjectId = [];

        $priceCandidates = DB::table('prices')
            ->where(function (\Illuminate\Database\Query\Builder $target) use ($ids, $objectIdByRoomId): void {
                $target->where(function (\Illuminate\Database\Query\Builder $direct) use ($ids): void {
                    $direct->where('priceable_type', Object_::class)->whereIn('priceable_id', $ids);
                });

                if ($objectIdByRoomId->isNotEmpty()) {
                    $target->orWhere(function (\Illuminate\Database\Query\Builder $viaRoom) use ($objectIdByRoomId): void {
                        $viaRoom->where('priceable_type', Room::class)->whereIn('priceable_id', $objectIdByRoomId->keys()->all());
                    });
                }
            })
            ->orderBy('amount')
            ->get(['priceable_type', 'priceable_id', 'amount', 'currency']);

        foreach ($priceCandidates as $row) {
            $objectId = $row->priceable_type === Object_::class
                ? (int) $row->priceable_id
                : (int) ($objectIdByRoomId[(int) $row->priceable_id] ?? 0);

            // Rows arrive ordered by amount ascending — the first row seen
            // for a given object is already its cheapest.
            if ($objectId === 0 || array_key_exists($objectId, $cheapestPriceByObjectId)) {
                continue;
            }

            $cheapestPriceByObjectId[$objectId] = $row;
        }

        $tiersByObjectId = DB::table('object_placements')
            ->join('placement_packages', 'placement_packages.id', '=', 'object_placements.placement_package_id')
            ->join('placement_tiers', 'placement_tiers.id', '=', 'placement_packages.placement_tier_id')
            ->leftJoin('placement_tier_translations', function (JoinClause $join): void {
                $join->on('placement_tier_translations.placement_tier_id', '=', 'placement_tiers.id')
                    ->where('placement_tier_translations.locale', '=', app()->getLocale());
            })
            ->whereIn('object_placements.object_id', $ids)
            ->get([
                'object_placements.object_id',
                'placement_tier_translations.badge_text',
                'placement_tiers.badge_colour',
                'placement_tiers.border_colour',
            ])
            ->keyBy(fn (object $row): int => (int) $row->object_id);

        foreach ($objects as $object) {
            $review = $reviewsByObjectId->get($object->id);
            $object->setAttribute(self::CARD_REVIEW_AVERAGE_KEY, $review !== null && $review->average !== null ? round((float) $review->average, 1) : null);
            $object->setAttribute(self::CARD_REVIEW_COUNT_KEY, (int) ($review->total ?? 0));

            $view = $viewCountsByObjectId->get($object->id);
            $object->setAttribute(self::CARD_VIEW_COUNT_KEY, (int) ($view->total ?? 0));

            $price = $cheapestPriceByObjectId[$object->id] ?? null;
            $object->setAttribute(self::CARD_PRICE_AMOUNT_KEY, $price !== null ? (string) $price->amount : null);
            $object->setAttribute(self::CARD_PRICE_CURRENCY_KEY, $price !== null ? (string) $price->currency : null);

            $tier = $tiersByObjectId->get($object->id);
            $object->setAttribute(self::CARD_TIER_BADGE_TEXT_KEY, $tier?->badge_text);
            $object->setAttribute(self::CARD_TIER_BADGE_COLOUR_KEY, $tier?->badge_colour);
            $object->setAttribute(self::CARD_TIER_BORDER_COLOUR_KEY, $tier?->border_colour);
        }
    }

    /**
     * Every criterion that can change the result set or its ordering, plus
     * the locale (translated name/label columns vary the rendered output
     * even when the row set does not).
     *
     * Every field {@see self::applyFilters()} reads must appear here, and
     * every axis {@see ScopeConstraint} carries must appear in the
     * constraint tuple — including axes no current call site narrows by.
     * A key that mirrors only the axes one call site happens to use is a
     * silent disclosure waiting for the first caller that passes the
     * column: two constraints differing solely in an omitted axis hash
     * identically, and the second actor is served the first one's rows.
     */
    private function cacheKey(CatalogSearchCriteria $criteria, ?ScopeConstraint $constraint = null): string
    {
        return 'catalog:search:'.hash('sha256', json_encode([
            'territoryId' => $criteria->territory?->id,
            'countryId' => $criteria->countryId,
            'objectTypeId' => $criteria->objectTypeId,
            'name' => $criteria->name,
            'amenityIds' => $criteria->amenityIds,
            'priceMin' => $criteria->priceMin,
            'priceMax' => $criteria->priceMax,
            'ratingMin' => $criteria->ratingMin,
            'attributeFilters' => $criteria->attributeFilters,
            'createdAfter' => $criteria->createdAfter?->toIso8601String(),
            'availabilityStatus' => $criteria->availabilityStatus,
            'page' => $criteria->page,
            'perPage' => $criteria->perPage,
            'locale' => app()->getLocale(),
            'constraint' => $constraint === null ? null : [
                $constraint->isUnrestricted,
                $constraint->countryIds,
                $constraint->territoryIds,
                $constraint->categoryIds,
            ],
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * The map's own retrieval path — the same scope and filters {@see
     * search()} applies, constrained to a viewport instead of paginated.
     * Tier ordering is deliberately not applied: pins are unordered
     * markers, not a ranked list, so {@see PlacementOrderingService}'s own
     * join buys nothing here. Only the tier's border colour is read, for
     * the marker's own visual treatment.
     *
     * Below {@see self::CLUSTER_THRESHOLD_ZOOM} the matched set is
     * aggregated into grid-cell centroids instead: a country-wide viewport
     * at launch volume matches tens of thousands of objects, and shipping
     * every one of them as an individual marker is a payload cost the
     * browser gains nothing from paying, since no visitor can distinguish
     * that many overlapping pins at that scale anyway.
     */
    public function pins(CatalogSearchCriteria $criteria, MapBounds $bounds, int $zoom): MapPinsResult
    {
        $query = Object_::query()->where('status', 'published')->whereNotNull('geom');

        $this->applyFilters($query, $criteria);

        $query->whereRaw(
            'geom && ST_MakeEnvelope(?, ?, ?, ?, 4326)::geography',
            [$bounds->southWestLng, $bounds->southWestLat, $bounds->northEastLng, $bounds->northEastLat],
        );

        if ($zoom < self::CLUSTER_THRESHOLD_ZOOM) {
            return MapPinsResult::clustered($this->clusterPins((clone $query), $zoom));
        }

        // Counted before the pin-mode select/limit are applied to the same
        // query builder, so this reflects the true match count rather than
        // the capped one below.
        $totalMatched = (clone $query)->count();

        $query->select([
            'objects.*',
            DB::raw('ST_Y(geom::geometry) as pin_lat'),
            DB::raw('ST_X(geom::geometry) as pin_lng'),
        ])->with(['placement.package.tier'])->limit(self::MAX_PINS_PER_RESPONSE);

        $pins = array_values($query->get()->map(function (Object_ $object): MapPin {
            $tier = $object->placement?->package?->tier;

            return new MapPin(
                objectId: $object->id,
                lat: (float) $object->getAttribute('pin_lat'),
                lng: (float) $object->getAttribute('pin_lng'),
                tierBorderColour: $tier instanceof PlacementTier ? $tier->border_colour : null,
            );
        })->all());

        return MapPinsResult::pins($pins, $totalMatched > self::MAX_PINS_PER_RESPONSE, $totalMatched);
    }

    /**
     * Groups the already-scoped, already-viewport-bounded matched set into
     * grid cells sized for `$zoom`, and averages each cell's coordinates
     * into one centroid. Grouped in PHP rather than via a PostGIS
     * `ST_SnapToGrid`/`ST_ClusterDBSCAN` aggregate: this is a plain
     * average over at most a few tens of thousands of rows — real volume,
     * but small enough that a second index-backed round trip through
     * PostGIS's clustering machinery buys nothing a `GROUP BY` in PHP does
     * not already give for free, correctly, in one query.
     *
     * @param  Builder<Object_>  $query  already filtered and bounded; only its select clause is replaced here
     * @return list<MapCluster>
     */
    private function clusterPins(Builder $query, int $zoom): array
    {
        $rows = $query->select([
            DB::raw('ST_Y(geom::geometry) as lat'),
            DB::raw('ST_X(geom::geometry) as lng'),
        ])->get();

        $cellSize = self::CLUSTER_BASE_CELL_DEGREES / (2 ** max($zoom, 0));

        /** @var array<string, array{latSum: float, lngSum: float, count: int}> $cells */
        $cells = [];

        foreach ($rows as $row) {
            $lat = (float) $row->getAttribute('lat');
            $lng = (float) $row->getAttribute('lng');
            $cellKey = (int) floor($lat / $cellSize).':'.(int) floor($lng / $cellSize);

            if (! array_key_exists($cellKey, $cells)) {
                $cells[$cellKey] = ['latSum' => 0.0, 'lngSum' => 0.0, 'count' => 0];
            }

            $cells[$cellKey]['latSum'] += $lat;
            $cells[$cellKey]['lngSum'] += $lng;
            $cells[$cellKey]['count']++;
        }

        return array_values(array_map(
            static fn (array $cell): MapCluster => new MapCluster(
                lat: $cell['latSum'] / $cell['count'],
                lng: $cell['lngSum'] / $cell['count'],
                count: $cell['count'],
            ),
            $cells,
        ));
    }

    /**
     * Applies every scope and filter criterion shared between the paginated
     * list retrieval and the map's pin retrieval — amenities, price,
     * rating, type-declared attributes, territory/type scope — so the two
     * surfaces can never silently disagree about what "the filtered
     * result set" means. Returns the resolved ordering scope for the one
     * caller ({@see search()}) that needs it.
     *
     * @param  Builder<Object_>  $query
     */
    private function applyFilters(Builder $query, CatalogSearchCriteria $criteria): Territory|ObjectType|null
    {
        $scope = $this->resolveScope($criteria);

        if ($criteria->territory instanceof Territory) {
            // Property access, not a ->descendantsAndSelf() method call: the
            // latter builds a fresh query (and re-runs the recursive CTE)
            // every time, even against the same $territory instance. The
            // property form goes through Eloquent's own relation cache, so a
            // caller that resolves this criterion's territory once and
            // passes the same instance into several search() calls -- every
            // catalog block on a territory page does exactly this, once per
            // active object type -- pays for the CTE once, not once per call.
            $territoryIds = $criteria->territory->descendantsAndSelf->pluck('id');
            $query->whereIn('territory_id', $territoryIds)
                // Redundant with territory_id (a territory's country is
                // immutable in practice) but deliberate: objects_scope_
                // ordering_index is (country_id, territory_id, object_type_id,
                // status), and a composite btree only serves a query that
                // constrains its columns as a leading prefix. Omitting this
                // would leave the territory-scoped hot path unable to use
                // that index at all.
                ->where('country_id', $criteria->territory->country_id);
        } elseif ($criteria->countryId !== null) {
            $query->where('country_id', $criteria->countryId);
        }

        if ($criteria->objectTypeId !== null) {
            // Table-qualified: PlacementOrderingService::apply() joins
            // placement_packages, which carries its own nullable
            // object_type_id (a package may target one type) — an
            // unqualified reference here is ambiguous the moment both are
            // present in the same query.
            $query->where('objects.object_type_id', $criteria->objectTypeId);
        }

        if ($criteria->name !== null && trim($criteria->name) !== '') {
            $this->applyNameSearch($query, $criteria->name);
        }

        foreach ($criteria->amenityIds as $amenityId) {
            $query->whereHas('amenities', fn (Builder $amenity): Builder => $amenity->where('amenities.id', $amenityId));
        }

        if ($criteria->priceMin !== null || $criteria->priceMax !== null) {
            $this->applyPriceFilter($query, $criteria->priceMin, $criteria->priceMax);
        }

        if ($criteria->ratingMin !== null) {
            $this->applyRatingFilter($query, $criteria->ratingMin);
        }

        foreach ($criteria->attributeFilters as $key => $value) {
            $this->applyAttributeFilter($query, $key, $value);
        }

        if ($criteria->createdAfter instanceof Carbon) {
            $query->where('objects.created_at', '>=', $criteria->createdAfter);
        }

        if ($criteria->availabilityStatus !== null) {
            $query->where('objects.availability_status', $criteria->availabilityStatus);
        }

        return $scope;
    }

    /**
     * The scope {@see PlacementOrderingService::apply()} reads bump recency
     * for. The service accepts exactly one scope value — a territory takes
     * precedence when both a territory and a type are selected, since a
     * bump "acts for the city, district, or resort the object belongs to";
     * a type-only view (no territory) scopes
     * to the type instead.
     */
    private function resolveScope(CatalogSearchCriteria $criteria): Territory|ObjectType|null
    {
        if ($criteria->territory instanceof Territory) {
            return $criteria->territory;
        }

        if ($criteria->objectType instanceof ObjectType) {
            return $criteria->objectType;
        }

        if ($criteria->objectTypeId !== null) {
            return ObjectType::query()->find($criteria->objectTypeId);
        }

        return null;
    }

    /**
     * Substring, case-insensitive, and typo-tolerant by index rather than
     * by pattern — `object_translations_name_trgm_index` (GIN,
     * `gin_trgm_ops`) is what keeps a leading-wildcard ILIKE off a
     * sequential scan.
     *
     * The visitor's term is escaped before it becomes a pattern. Binding
     * alone stops SQL injection but not *pattern* injection: an unescaped
     * `%` or `_` in the term is still a LIKE metacharacter, so `?q=%`
     * silently degrades the search into "match every object" — a wrong
     * result set, and the one query shape the trigram index cannot serve.
     * Postgres reads `\` as LIKE's default escape character, so all three
     * of `\`, `%`, and `_` are escaped with it.
     *
     * @param  Builder<Object_>  $query
     */
    private function applyNameSearch(Builder $query, string $name): void
    {
        $pattern = '%'.addcslashes($name, '\\%_').'%';

        $query->whereHas(
            'translations',
            fn (Builder $translation): Builder => $translation->where('name', 'ilike', $pattern),
        );
    }

    /**
     * An object's price range is never a column on the object itself — it is
     * derived from {@see Price} rows attached either directly to
     * the object (e.g. a restaurant's average cheque) or to one of its rooms.
     * Matches an object with at least one price row, of either kind, inside
     * the requested range.
     *
     * @param  Builder<Object_>  $query
     */
    private function applyPriceFilter(Builder $query, ?float $min, ?float $max): void
    {
        $query->whereExists(function (\Illuminate\Database\Query\Builder $priceQuery) use ($min, $max): void {
            $priceQuery->select(DB::raw(1))
                ->from('prices')
                ->where(function (\Illuminate\Database\Query\Builder $target): void {
                    $target->where(function (\Illuminate\Database\Query\Builder $direct): void {
                        $direct->where('prices.priceable_type', Object_::class)
                            ->whereColumn('prices.priceable_id', 'objects.id');
                    })->orWhere(function (\Illuminate\Database\Query\Builder $viaRoom): void {
                        $viaRoom->where('prices.priceable_type', Room::class)
                            ->whereIn('prices.priceable_id', function (\Illuminate\Database\Query\Builder $roomIds): void {
                                $roomIds->select('id')->from('rooms')->whereColumn('rooms.object_id', 'objects.id');
                            });
                    });
                });

            if ($min !== null) {
                $priceQuery->where('prices.amount', '>=', $min);
            }

            if ($max !== null) {
                $priceQuery->where('prices.amount', '<=', $max);
            }
        });
    }

    /**
     * `objects.rating` does not exist — no maintained aggregate backs it yet
     * (the same documented absence {@see PlacementOrderingService} leaves
     * out of its own ordering contract). Filtering by rating reads a fresh
     * average directly from published, non-deleted reviews rather than a
     * fabricated column.
     *
     * @param  Builder<Object_>  $query
     */
    private function applyRatingFilter(Builder $query, float $min): void
    {
        $query->whereExists(function (\Illuminate\Database\Query\Builder $reviewQuery) use ($min): void {
            $reviewQuery->select(DB::raw(1))
                ->from('reviews')
                ->whereColumn('reviews.object_id', 'objects.id')
                ->where('reviews.status', 'published')
                ->whereNull('reviews.deleted_at')
                ->groupBy('reviews.object_id')
                ->havingRaw('avg(reviews.rating) >= ?', [$min]);
        });
    }

    /**
     * Filters against the object's own type-declared `attributes` (jsonb) —
     * "distance to sea", "catering type", and every other field a type's own
     * `attribute_schema` names rather than a fixed column. The key is never
     * interpolated into the SQL string — it is bound
     * as a query parameter to the `->>` operator's own text operand, both
     * because a request-supplied key must never be trusted into raw SQL and
     * because a literal, non-interpolated SQL string is what static analysis
     * (and, more importantly, injection safety) requires.
     *
     * @param  Builder<Object_>  $query
     * @param  array{min?: float, max?: float}|scalar  $value
     */
    private function applyAttributeFilter(Builder $query, string $key, array|string|int|float|bool $value): void
    {
        if (is_array($value)) {
            if (array_key_exists('min', $value)) {
                $query->whereRaw('(attributes ->> ?)::numeric >= ?', [$key, $value['min']]);
            }

            if (array_key_exists('max', $value)) {
                $query->whereRaw('(attributes ->> ?)::numeric <= ?', [$key, $value['max']]);
            }

            return;
        }

        $query->whereRaw('attributes ->> ? = ?', [$key, (string) $value]);
    }
}
