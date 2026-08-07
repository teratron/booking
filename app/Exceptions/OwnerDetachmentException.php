<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when detachment is requested on an object that already has no
 * owner — there is nothing to detach.
 */
final class OwnerDetachmentException extends RuntimeException
{
    public static function forObject(int $objectId): self
    {
        return new self("Object [{$objectId}] has no owner to detach.");
    }
}
