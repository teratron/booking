<?php

declare(strict_types=1);

namespace App\Support\Catalog;

/**
 * One resolved, clickable contact action on a card — a direct hand-off to
 * the owner. The card is a conversion surface, not only a navigation
 * surface.
 */
final readonly class ObjectCardContactAction
{
    public function __construct(
        public int $contactChannelTypeId,
        public string $channelKey,
        public string $label,
        public string $href,
    ) {}
}
