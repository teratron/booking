<?php

declare(strict_types=1);

namespace App\Support\Advertising;

/**
 * The placement tier's own card presentation, carried through to a card's
 * decoration payload unchanged — this is display data only, never a rank:
 * an object's actual precedence lives entirely in `placement_tiers.rank` and
 * the catalog ordering query, not in anything read through this object.
 */
final readonly class TierBadge
{
    public function __construct(
        public string $borderColour,
        public string $badgeColour,
        public ?string $icon,
        public ?string $text,
    ) {}
}
