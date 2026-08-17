<?php

declare(strict_types=1);

namespace App\Support\Catalog;

use App\Models\ObjectType;
use App\Models\Territory;
use App\Services\Catalog\CatalogQueryService;
use Illuminate\Support\Carbon;

/**
 * Everything a catalog surface (home, territory page, catalog/search page,
 * an object's own nearby/similar block) may narrow a listing by. Every
 * public listing surface builds one of these and hands it to
 * {@see CatalogQueryService::search()} — no surface
 * builds its own retrieval query.
 *
 * Facet resolution (which amenity groups and type-declared attributes apply
 * to a given object type) is a page-level concern, not this DTO's: a caller
 * is expected to have already resolved `$amenityIds` against the registry
 * before constructing this criteria object.
 */
final readonly class CatalogSearchCriteria
{
    /**
     * @param  list<int>  $amenityIds  AND semantics — an object must carry every one
     * @param  array<string, array{min?: float, max?: float}|scalar>  $attributeFilters  type-declared `attributes` keys; a `min`/`max` array applies a numeric range, a scalar applies an exact match
     */
    public function __construct(
        public ?Territory $territory = null,
        /** Country-wide scoping with no specific territory — the home page's own blocks. Ignored when `$territory` is set, which already implies its own country. */
        public ?int $countryId = null,
        public ?int $objectTypeId = null,
        /**
         * The same object type `$objectTypeId` names, already hydrated —
         * an optional hint so a caller that resolved it anyway (to render
         * a filter label, say) hands it over instead of making
         * {@see CatalogQueryService}'s own ordering-scope resolution fetch
         * the identical row a second time. `$objectTypeId` still drives
         * filtering either way; this changes only how the scope is
         * resolved, never what result set or ordering the criteria means.
         */
        public ?ObjectType $objectType = null,
        public ?string $name = null,
        public array $amenityIds = [],
        public ?float $priceMin = null,
        public ?float $priceMax = null,
        public ?float $ratingMin = null,
        public array $attributeFilters = [],
        /** The home page's own "newly added objects" block filter — absent everywhere else. */
        public ?Carbon $createdAfter = null,
        /** `available` | `unavailable` | `unspecified` — the public API's own `?availability=` filter, absent everywhere else. */
        public ?string $availabilityStatus = null,
        public int $page = 1,
        public int $perPage = 24,
    ) {}
}
