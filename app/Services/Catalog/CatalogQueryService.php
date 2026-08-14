<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Models\Object_;
use App\Models\ObjectType;
use App\Models\PlacementTier;
use App\Models\Price;
use App\Models\Room;
use App\Models\Territory;
use App\Services\Placement\PlacementOrderingService;
use App\Support\Catalog\CatalogSearchCriteria;
use App\Support\Catalog\MapBounds;
use App\Support\Catalog\MapPin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
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
    public function __construct(
        private readonly PlacementOrderingService $ordering,
    ) {}

    /**
     * @return LengthAwarePaginator<int, Object_>
     */
    public function search(CatalogSearchCriteria $criteria): LengthAwarePaginator
    {
        $query = Object_::query()->where('status', 'published');

        $scope = $this->applyFilters($query, $criteria);

        $this->ordering->apply($query, $scope);

        return $query->paginate($criteria->perPage, ['*'], 'page', $criteria->page);
    }

    /**
     * The map's own retrieval path — the same scope and filters {@see
     * search()} applies, constrained to a viewport instead of paginated.
     * Tier ordering is deliberately not applied: pins are unordered
     * markers, not a ranked list, so {@see PlacementOrderingService}'s own
     * join buys nothing here. Only the tier's border colour is read, for
     * the marker's own visual treatment.
     *
     * @return list<MapPin>
     */
    public function pins(CatalogSearchCriteria $criteria, MapBounds $bounds): array
    {
        $query = Object_::query()->where('status', 'published')->whereNotNull('geom');

        $this->applyFilters($query, $criteria);

        $query->whereRaw(
            'geom && ST_MakeEnvelope(?, ?, ?, ?, 4326)::geography',
            [$bounds->southWestLng, $bounds->southWestLat, $bounds->northEastLng, $bounds->northEastLat],
        );

        $query->select([
            'objects.*',
            DB::raw('ST_Y(geom::geometry) as pin_lat'),
            DB::raw('ST_X(geom::geometry) as pin_lng'),
        ])->with(['placement.package.tier']);

        return array_values($query->get()->map(function (Object_ $object): MapPin {
            $tier = $object->placement?->package?->tier;

            return new MapPin(
                objectId: $object->id,
                lat: (float) $object->getAttribute('pin_lat'),
                lng: (float) $object->getAttribute('pin_lng'),
                tierBorderColour: $tier instanceof PlacementTier ? $tier->border_colour : null,
            );
        })->all());
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
            $territoryIds = $criteria->territory->descendantsAndSelf()->pluck('id');
            $query->whereIn('territory_id', $territoryIds)
                // Redundant with territory_id (a territory's country is
                // immutable in practice) but deliberate: objects_scope_
                // ordering_index is (country_id, territory_id, object_type_id,
                // status), and a composite btree only serves a query that
                // constrains its columns as a leading prefix. Omitting this
                // would leave the territory-scoped hot path unable to use
                // that index at all.
                ->where('country_id', $criteria->territory->country_id);
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

        return $scope;
    }

    /**
     * The scope {@see PlacementOrderingService::apply()} reads bump recency
     * for. The service accepts exactly one scope value — a territory takes
     * precedence when both a territory and a type are selected, since a
     * bump "acts for the city, district, or resort the object belongs to"
     * (the spec's own §5.3 wording); a type-only view (no territory) scopes
     * to the type instead.
     */
    private function resolveScope(CatalogSearchCriteria $criteria): Territory|ObjectType|null
    {
        if ($criteria->territory instanceof Territory) {
            return $criteria->territory;
        }

        if ($criteria->objectTypeId !== null) {
            return ObjectType::query()->find($criteria->objectTypeId);
        }

        return null;
    }

    /**
     * @param  Builder<Object_>  $query
     */
    private function applyNameSearch(Builder $query, string $name): void
    {
        $query->whereHas(
            'translations',
            fn (Builder $translation): Builder => $translation->where('name', 'ilike', '%'.$name.'%'),
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
