<?php

declare(strict_types=1);

namespace App\Support\Shell;

/**
 * A validated visitor submission from the shared feedback overlay, ready to
 * persist.
 */
final readonly class FeedbackSubmissionData
{
    public function __construct(
        public string $name,
        public string $email,
        public ?string $phone,
        public string $message,
        public string $pageUrl,
        public string $locale,
    ) {}
}
