<?php

declare(strict_types=1);

namespace App\Services\Moderation;

use App\Models\ModerationRequest;

/**
 * What a submitted change should do next, decided once and returned as a
 * single exclusive result. A caller cannot both apply the change and hold an
 * enqueued request — the two outcomes are mutually exclusive by
 * construction, which is what keeps "a pending edit never overwrites the
 * published version" true regardless of what the caller does with the
 * result.
 */
final readonly class ModerationOutcome
{
    private function __construct(
        public bool $shouldPublishImmediately,
        public ?ModerationRequest $request,
    ) {}

    public static function publishImmediately(): self
    {
        return new self(true, null);
    }

    public static function enqueued(ModerationRequest $request): self
    {
        return new self(false, $request);
    }
}
