<?php

declare(strict_types=1);

namespace App\Support\Reviews;

/**
 * A validated visitor submission from the public review form, ready to
 * persist. `authorName` is always present — this portal has no public
 * visitor registration, so every submission is a named-guest review.
 */
final readonly class ReviewSubmissionData
{
    public function __construct(
        public string $authorName,
        public int $rating,
        public string $body,
        public ?string $captchaResponse,
    ) {}
}
