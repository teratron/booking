<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Services\Integrations\CaptchaVerifier;
use App\Services\Reviews\ReviewSubmissionGate;
use RuntimeException;

/**
 * Raised when a review submission is refused server-side — the enforcement
 * point behind {@see ReviewSubmissionGate} and
 * {@see CaptchaVerifier}, neither of which is a
 * mere usability affordance the form can bypass by never showing.
 */
final class ReviewSubmissionRefusedException extends RuntimeException
{
    public static function gateClosed(int $objectId): self
    {
        return new self("Review submission for object [{$objectId}] is not reachable in the current session.");
    }

    public static function captchaFailed(): self
    {
        return new self('Review submission refused: CAPTCHA verification failed.');
    }
}
