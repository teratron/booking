<?php

declare(strict_types=1);

namespace App\Support\Shell;

/**
 * One entry in the header's language switcher, sourced from the active
 * language registry.
 */
final readonly class PublicLanguageOption
{
    public function __construct(
        public string $code,
        public string $shortLabel,
        public bool $isPrimary,
    ) {}
}
