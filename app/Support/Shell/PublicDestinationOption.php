<?php

declare(strict_types=1);

namespace App\Support\Shell;

/**
 * One entry in the footer's "popular destinations" list — a top-level
 * territory, linking to its own landing page.
 */
final readonly class PublicDestinationOption
{
    public function __construct(
        public int $id,
        public string $name,
    ) {}
}
