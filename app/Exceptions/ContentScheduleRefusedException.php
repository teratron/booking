<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when a schedule action on a `NewsItem` or `Promotion` names a
 * publish date that is not actually in the future. Scheduling something for
 * the past is not scheduling — it is a confusing way to publish immediately,
 * which the dedicated publish action already does explicitly. Shared across
 * both content types rather than duplicated per type, the way
 * `ArticleScheduleRefusedException` was before this one — the two are kept
 * distinct because unifying them would mean revisiting `Article`'s own
 * already-shipped, already-tested exception for no behavioural gain.
 */
final class ContentScheduleRefusedException extends RuntimeException
{
    public static function notInFuture(string $contentType, int $id): self
    {
        return new self("{$contentType} [{$id}] cannot be scheduled for a date that is not in the future.");
    }
}
