<?php

declare(strict_types=1);

namespace App\Support\Shell;

/**
 * One entry in the public site's header navigation, derived from the
 * object-type registry rather than hard-coded — a top-level type with no
 * children renders as a direct link, one with children renders as a
 * dropdown group.
 */
final readonly class PublicNavigationEntry
{
    /** @param list<self> $children */
    public function __construct(
        public int $id,
        public string $key,
        public string $name,
        public array $children,
    ) {}
}
