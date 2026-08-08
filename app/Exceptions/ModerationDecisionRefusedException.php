<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class ModerationDecisionRefusedException extends RuntimeException
{
    public static function alreadyDecided(int $requestId, string $decision): self
    {
        return new self("Moderation request [{$requestId}] was already decided ({$decision}) and cannot be decided again.");
    }

    public static function partialAcceptanceDisabled(int $requestId): self
    {
        return new self("Partial acceptance is disabled by the portal's moderation settings — request [{$requestId}] must be approved or rejected as a whole.");
    }

    public static function targetMissing(int $requestId): self
    {
        return new self("Moderation request [{$requestId}]'s target no longer exists — it cannot be applied.");
    }
}
