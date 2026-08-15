<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when a proposed slug (or the full path it resolves to) is already
 * claimed as the origin of an active redirect — reusing it would make that
 * redirect's own promise false and leave two records competing for one
 * address.
 */
final class SlugReuseRefusedException extends RuntimeException
{
    public static function forPath(string $locale, string $path): self
    {
        return new self("The path [{$locale}/{$path}] is already claimed by an active redirect and cannot be reused as a live address.");
    }
}
