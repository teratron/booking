<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when a bump cannot proceed — the package does not allow bumping,
 * the minimum interval between free bumps has not elapsed, or the free
 * bump allowance for the current placement period is exhausted.
 */
final class BumpRefusedException extends RuntimeException
{
    public static function notAllowedByPackage(int $packageId): self
    {
        return new self("Placement package [{$packageId}] does not allow bumping.");
    }

    public static function intervalNotElapsed(int $objectId, int $intervalHours): self
    {
        return new self("Object [{$objectId}] must wait the full {$intervalHours}-hour interval since its last free bump.");
    }

    public static function allowanceExhausted(int $objectId, int $freeBumpsPerPeriod): self
    {
        return new self("Object [{$objectId}] has used its {$freeBumpsPerPeriod} free bump(s) for the current placement period.");
    }

    public static function noCurrentPlacement(int $objectId): self
    {
        return new self("Object [{$objectId}] has no current placement to bump.");
    }
}
