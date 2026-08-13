<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when a schedule action names a `publish_at` that is not actually in
 * the future. Scheduling something for the past is not scheduling — it is a
 * confusing way to publish immediately, which the dedicated publish action
 * already does explicitly.
 */
final class ArticleScheduleRefusedException extends RuntimeException
{
    public static function notInFuture(int $articleId): self
    {
        return new self("Article [{$articleId}] cannot be scheduled for a date that is not in the future.");
    }
}
