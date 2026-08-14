<?php

declare(strict_types=1);

namespace App\Support\Cabinet;

use App\Models\ModerationRequest;
use App\Services\Moderation\ModerationPipeline;
use Illuminate\Database\Eloquent\Model;

/**
 * What happened to one owner-authored content submission (a news item or a
 * promotion) — published straight to the live record, or withheld behind a
 * pending {@see ModerationRequest}.
 *
 * Mirrors {@see ObjectEditOutcome}'s own mutually
 * exclusive shape for the content-authoring screens' own concern. Unlike an
 * object edit, a content submission always has a concrete row to hand back —
 * the row is created before moderation is ever consulted, since
 * {@see ModerationPipeline::submit()} requires a
 * persisted target — so both factories carry it.
 */
final readonly class ContentSubmissionOutcome
{
    private function __construct(
        public bool $applied,
        public Model $record,
        public ?ModerationRequest $request,
    ) {}

    public static function applied(Model $record): self
    {
        return new self(true, $record, null);
    }

    public static function queued(Model $record, ?ModerationRequest $request): self
    {
        return new self(false, $record, $request);
    }
}
