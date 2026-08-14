<?php

declare(strict_types=1);

namespace App\Support\Catalog;

/**
 * The visible map viewport, used to constrain pin retrieval to what a
 * visitor can actually see rather than every matching object portal-wide.
 */
final readonly class MapBounds
{
    public function __construct(
        public float $southWestLat,
        public float $southWestLng,
        public float $northEastLat,
        public float $northEastLng,
    ) {}
}
