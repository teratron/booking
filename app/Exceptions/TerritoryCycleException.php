<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when a reparent would make a territory its own ancestor — moving a
 * node underneath one of its own descendants, or underneath itself.
 */
final class TerritoryCycleException extends RuntimeException
{
    public static function forMove(int $territoryId, int $newParentId): self
    {
        return new self("Territory [{$territoryId}] cannot be moved under [{$newParentId}]: it is that territory's own ancestor.");
    }
}
