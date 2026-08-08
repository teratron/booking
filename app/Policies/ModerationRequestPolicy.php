<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ModerationRequest;
use App\Models\User;

/**
 * Scoped on the country denormalized onto the request at submission time —
 * a country-scoped moderator's queue must show only their own country's
 * entries, the same posture every other country-scoped resource in this
 * panel takes.
 */
final class ModerationRequestPolicy extends ScopedPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('moderation.view');
    }

    public function view(User $user, ModerationRequest $moderationRequest): bool
    {
        return $this->authorize($user, 'moderation.view', $moderationRequest->country_id);
    }

    public function update(User $user, ModerationRequest $moderationRequest): bool
    {
        return $this->authorize($user, 'moderation.edit', $moderationRequest->country_id);
    }
}
