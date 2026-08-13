<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when an administrator broadcast is attempted after the day's
 * `notifications.broadcast_rate_limit` quota is already spent.
 *
 * Thrown before a single recipient is touched — {@see
 * \App\Services\Notifications\BroadcastComposer::send()} checks the quota
 * first, so catching this always means nothing was sent, never a partial
 * broadcast. The composing screen catches it and shows a plain refusal
 * rather than letting it surface as an unhandled error.
 */
final class BroadcastRateLimitedException extends RuntimeException
{
    public static function forLimit(int $limit): self
    {
        return new self("The daily broadcast limit of {$limit} has already been reached; no message was sent.");
    }
}
