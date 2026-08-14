<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Notification;
use App\Models\User;

/**
 * A notification belongs to exactly one recipient — this account's own
 * inbox, never another account's, and never a staff-side view (no back
 * office screen browses notifications by recipient; if one is added later
 * it earns its own authorization path rather than widening this one).
 */
final class NotificationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Notification $notification): bool
    {
        return $notification->recipient_id === $user->id;
    }

    public function update(User $user, Notification $notification): bool
    {
        return $notification->recipient_id === $user->id;
    }
}
