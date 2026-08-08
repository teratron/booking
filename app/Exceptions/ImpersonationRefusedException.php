<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when an attempt to enter support mode fails one of its own guards:
 * the actor lacks the permission, the actor is itself an owner-scoped
 * account, or the target does not hold the owner role this feature exists
 * to let staff step into.
 */
final class ImpersonationRefusedException extends RuntimeException
{
    public static function actorNotPermitted(int $actorId): self
    {
        return new self("User [{$actorId}] does not hold the permission required to enter support mode.");
    }

    public static function actorIsOwnerScoped(int $actorId): self
    {
        return new self("User [{$actorId}] holds the owner role and cannot enter support mode as anyone.");
    }

    public static function targetIsNotAnOwner(int $targetId): self
    {
        return new self("User [{$targetId}] does not hold the owner role and cannot be impersonated.");
    }
}
