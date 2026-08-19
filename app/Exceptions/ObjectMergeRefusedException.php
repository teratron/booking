<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when an administrator-confirmed object merge cannot proceed —
 * caught by the Filament action and surfaced as a notification rather than
 * a fatal error, the same pattern this codebase already uses for
 * {@see BumpRefusedException} and {@see AvailabilityRevertRefusedException}.
 */
final class ObjectMergeRefusedException extends RuntimeException
{
    public static function sameRecord(int $objectId): self
    {
        return new self("Object [{$objectId}] cannot be merged with itself.");
    }

    public static function alreadyArchived(int $objectId, string $role): self
    {
        return new self("Object [{$objectId}] cannot be the {$role} of a merge — it is already archived.");
    }
}
