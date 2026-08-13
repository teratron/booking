<?php

declare(strict_types=1);

namespace App\Support\Advertising;

/**
 * The exact, bounded set of decoration data a catalog card is allowed to
 * render: at most one tier badge, one promotion label, and one availability
 * badge. Each slot is a single nullable property rather than a collection,
 * so "at most one of each" is a fact about this class's shape — a caller
 * cannot accumulate a second tier badge onto the same payload, because there
 * is nowhere on the object to put it.
 */
final readonly class CardDecorationPayload
{
    public function __construct(
        public ?TierBadge $tierBadge,
        public ?PromotionLabelDecoration $promotionLabel,
        public ?AvailabilityBadge $availabilityBadge,
    ) {}
}
