<?php

declare(strict_types=1);

namespace App\Support\Reviews;

use App\Http\Controllers\Public\ObjectPageController;

/**
 * What the object page's review-submission form needs to render itself
 * correctly for the current visitor and the portal's current configuration
 * — resolved once per request by {@see ObjectPageController},
 * never recomputed inside the view.
 */
final readonly class ReviewFormViewData
{
    public function __construct(
        public string $mode,
        public bool $canSubmit,
        public bool $captchaEnabled,
        public string $captchaSiteKey,
    ) {}
}
