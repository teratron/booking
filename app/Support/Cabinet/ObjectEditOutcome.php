<?php

declare(strict_types=1);

namespace App\Support\Cabinet;

use App\Models\ModerationRequest;
use App\Services\Moderation\ModerationOutcome;

/**
 * What happened to one submitted object edit — applied straight to the live
 * record, or left untouched behind a pending {@see ModerationRequest}.
 *
 * Mirrors {@see ModerationOutcome}'s own
 * mutually-exclusive shape, but for the cabinet edit page's own concern:
 * whether the page should write the submitted data at all, not merely
 * whether the underlying moderation call resolved to immediate publication —
 * a draft (never moderated) and an immediately-published edit both come back
 * as `applied()`, even though only the second one ever reached the pipeline.
 */
final readonly class ObjectEditOutcome
{
    private function __construct(
        public bool $applied,
        public ?ModerationRequest $request,
    ) {}

    public static function applied(): self
    {
        return new self(true, null);
    }

    public static function queued(?ModerationRequest $request): self
    {
        return new self(false, $request);
    }
}
