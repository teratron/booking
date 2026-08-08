<?php

declare(strict_types=1);

namespace App\Services\Moderation;

use App\Models\ModerationRequest;
use App\Models\User;
use App\Services\Audit\AuditJournal;

/**
 * Administers the moderation queue itself — currently just reassignment.
 * Deciding a request (approve, reject, request revision) belongs to the
 * review screen this queue hands off to, not here.
 */
final class ModerationQueueService
{
    public function __construct(private readonly AuditJournal $journal) {}

    public function reassign(ModerationRequest $request, User $moderator, User $actor): void
    {
        $previous = $request->assigned_moderator_id;

        $request->forceFill(['assigned_moderator_id' => $moderator->id])->save();

        $this->journal->record(
            'moderation_request_reassigned',
            $request,
            ['assigned_moderator_id' => $previous],
            ['assigned_moderator_id' => $moderator->id],
            $actor,
            ['moderation'],
        );
    }
}
