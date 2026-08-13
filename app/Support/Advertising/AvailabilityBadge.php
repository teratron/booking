<?php

declare(strict_types=1);

namespace App\Support\Advertising;

/**
 * The availability badge a card is allowed to carry — one of the three
 * decoration slots a card is bounded to, alongside the tier badge and the
 * promotion label. `status` is one of the values the `objects` table's own
 * check constraint already permits (`available`, `unavailable`,
 * `unspecified`); this class does not re-open that set, it only carries it.
 */
final readonly class AvailabilityBadge
{
    public function __construct(
        public string $status,
    ) {}
}
