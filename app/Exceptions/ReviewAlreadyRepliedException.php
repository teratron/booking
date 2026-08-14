<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when an owner attempts a second reply to a review that already
 * carries one — an owner may reply exactly once per review.
 */
final class ReviewAlreadyRepliedException extends RuntimeException
{
    public static function forReview(int $reviewId): self
    {
        return new self("Review [{$reviewId}] already has an owner reply.");
    }
}
