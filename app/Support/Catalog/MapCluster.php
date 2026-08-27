<?php

declare(strict_types=1);

namespace App\Support\Catalog;

/**
 * A server-side aggregate of pins too dense to render individually at the
 * current zoom level — a grid-cell centroid and how many published objects
 * fell inside that cell. Returned instead of individual {@see MapPin}
 * markers below the catalog map's own clustering threshold zoom.
 */
final readonly class MapCluster
{
    public function __construct(
        public float $lat,
        public float $lng,
        public int $count,
    ) {}
}
