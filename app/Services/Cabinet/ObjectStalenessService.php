<?php

declare(strict_types=1);

namespace App\Services\Cabinet;

use App\Jobs\StalenessSweepJob;
use App\Models\Notification;
use App\Models\NotificationType;
use App\Models\Object_;

/**
 * Reads whatever staleness state {@see StalenessSweepJob} has
 * already produced for an object — this service adds no detection logic of
 * its own, only a query against the `information_out_of_date` notification
 * the sweep already wrote for it.
 *
 * A notification only counts as a live "still stale" signal while it
 * postdates the object's own last edit: once an owner updates the object,
 * `updated_at` moves past the notification's `created_at` and the flag
 * clears on its own, without this service re-deriving the sweep's own
 * `updated_at`-versus-threshold condition a second time.
 */
final class ObjectStalenessService
{
    public function isFlagged(Object_ $object): bool
    {
        $type = NotificationType::query()->where('key', 'information_out_of_date')->first();

        if (! $type instanceof NotificationType) {
            return false;
        }

        return Notification::query()
            ->where('related_type', $object->getMorphClass())
            ->where('related_id', $object->id)
            ->where('notification_type_id', $type->id)
            ->where('created_at', '>=', $object->updated_at)
            ->exists();
    }
}
