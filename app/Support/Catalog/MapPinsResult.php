<?php

declare(strict_types=1);

namespace App\Support\Catalog;

/**
 * The map's own retrieval outcome — one of two mutually exclusive shapes,
 * decided by the requested zoom relative to the catalog map's own
 * clustering threshold zoom: grid-cell {@see MapCluster} aggregates below
 * it, individual {@see MapPin} markers at or past it. `$clustered` is
 * carried explicitly rather than inferred from which array is empty — an
 * empty viewport in pin mode and an empty viewport in cluster mode both
 * legitimately produce two empty arrays, and the caller must still know
 * which JSON shape to render.
 */
final readonly class MapPinsResult
{
    /**
     * @param  list<MapPin>  $pins
     * @param  list<MapCluster>  $clusters
     */
    private function __construct(
        public bool $clustered,
        public array $pins,
        public array $clusters,
        public bool $truncated,
        public int $totalMatched,
    ) {}

    /** @param  list<MapCluster>  $clusters */
    public static function clustered(array $clusters): self
    {
        return new self(clustered: true, pins: [], clusters: $clusters, truncated: false, totalMatched: 0);
    }

    /** @param  list<MapPin>  $pins */
    public static function pins(array $pins, bool $truncated, int $totalMatched): self
    {
        return new self(clustered: false, pins: $pins, clusters: [], truncated: $truncated, totalMatched: $totalMatched);
    }
}
