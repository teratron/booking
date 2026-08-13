<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\NotificationPreference;
use App\Models\NotificationType;
use App\Models\User;

/**
 * Reads and writes one recipient's on/off switch for one notification type.
 * Backed by `notification_preferences`, keyed on (user, type) — absence of a
 * row is a distinct, meaningful state ("not yet configured") from an
 * explicit `is_enabled = false` row, and both are exposed here as a single
 * boolean so callers never need to know the difference themselves.
 */
final class NotificationPreferenceService
{
    /**
     * Whether $recipient currently wants $type delivered outbound. Defaults
     * to true when no preference row exists — an unconfigured preference is
     * never treated as a suppression.
     */
    public function isEnabled(User $recipient, NotificationType $type): bool
    {
        $preference = NotificationPreference::query()
            ->where('user_id', $recipient->id)
            ->where('notification_type_id', $type->id)
            ->first();

        if (! $preference instanceof NotificationPreference) {
            return true;
        }

        return $preference->is_enabled;
    }

    /**
     * Sets $recipient's preference for $type, creating the row on its first
     * write and updating it thereafter. Callers are expected to gate this to
     * optional-class types in the UI; nothing here refuses a write against a
     * transactional type; the dispatch pipeline simply never reads it for one.
     */
    public function setEnabled(User $recipient, NotificationType $type, bool $enabled): void
    {
        NotificationPreference::query()->updateOrCreate(
            ['user_id' => $recipient->id, 'notification_type_id' => $type->id],
            ['is_enabled' => $enabled],
        );
    }
}
