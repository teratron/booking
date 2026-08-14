<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when an owner attempts to report a review a second time — a report
 * already in place must be resolved by an administrator before another one
 * can be filed, rather than silently overwriting the original reason.
 */
final class ReviewAlreadyReportedException extends RuntimeException
{
    public static function forReview(int $reviewId): self
    {
        return new self("Review [{$reviewId}] has already been reported.");
    }
}
