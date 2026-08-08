<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when a revert is attempted on an object that has never had a prior
 * availability status recorded — there is nothing for "previous" to mean.
 */
final class AvailabilityRevertRefusedException extends RuntimeException
{
    public static function noPriorStatus(int $objectId): self
    {
        return new self("Object [{$objectId}] has no prior availability status to revert to.");
    }
}
