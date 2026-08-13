<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when an upload would push an object's photo gallery past the
 * portal's configured maximum photo count.
 */
final class PhotoLimitExceededException extends RuntimeException
{
    public static function forObject(int $objectId, int $maxPhotos): self
    {
        return new self("Object [{$objectId}] already holds the maximum of {$maxPhotos} photo(s).");
    }
}
