<?php

declare(strict_types=1);

namespace App\Support\Shell;

/**
 * One entry in the header's country switcher, sourced from the active
 * country registry.
 */
final readonly class PublicCountryOption
{
    public function __construct(
        public int $id,
        public string $code,
        public string $name,
        public ?string $flagPath,
    ) {}
}
