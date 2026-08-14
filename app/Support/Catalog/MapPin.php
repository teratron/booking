<?php

declare(strict_types=1);

namespace App\Support\Catalog;

/**
 * One marker on the catalog map — deliberately lean, since a viewport can
 * carry hundreds of these. The compact card a pin opens on click is
 * fetched separately, on demand, reusing the same presenter every list
 * card renders through.
 */
final readonly class MapPin
{
    public function __construct(
        public int $objectId,
        public float $lat,
        public float $lng,
        public ?string $tierBorderColour,
    ) {}
}
