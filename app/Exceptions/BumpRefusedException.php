<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when a bump cannot proceed — the package does not allow bumping,
 * the minimum interval between free bumps has not elapsed, or the free
 * bump allowance for the current placement period is exhausted.
 *
 * `$reasonKey` and the two optional numeric properties exist so a caller
 * that needs an owner-presentable message (the cabinet, unlike the back
 * office, cannot show `getMessage()`'s raw developer-facing text) can
 * resolve its own translated copy without re-deriving which refusal
 * happened from the message string.
 */
final class BumpRefusedException extends RuntimeException
{
    private function __construct(
        string $message,
        public readonly string $reasonKey,
        public readonly ?int $intervalHours = null,
        public readonly ?int $freeBumpsPerPeriod = null,
    ) {
        parent::__construct($message);
    }

    public static function notAllowedByPackage(int $packageId): self
    {
        return new self("Placement package [{$packageId}] does not allow bumping.", 'not_allowed_by_package');
    }

    public static function intervalNotElapsed(int $objectId, int $intervalHours): self
    {
        return new self(
            "Object [{$objectId}] must wait the full {$intervalHours}-hour interval since its last free bump.",
            'interval_not_elapsed',
            intervalHours: $intervalHours,
        );
    }

    public static function allowanceExhausted(int $objectId, int $freeBumpsPerPeriod): self
    {
        return new self(
            "Object [{$objectId}] has used its {$freeBumpsPerPeriod} free bump(s) for the current placement period.",
            'allowance_exhausted',
            freeBumpsPerPeriod: $freeBumpsPerPeriod,
        );
    }

    public static function noCurrentPlacement(int $objectId): self
    {
        return new self("Object [{$objectId}] has no current placement to bump.", 'no_current_placement');
    }
}
