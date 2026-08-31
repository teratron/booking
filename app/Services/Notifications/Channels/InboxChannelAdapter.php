<?php

declare(strict_types=1);

namespace App\Services\Notifications\Channels;

use App\Models\Notification;
use App\Models\NotificationDispatch;

/**
 * The inbox has nothing to deliver — the notification record itself is the
 * inbox entry: "the notification is a record, not a send". This
 * adapter exists only so `inbox` is a real, registered channel like any
 * other rather than a special case the dispatch pipeline has to know about.
 */
final class InboxChannelAdapter implements NotificationChannelAdapter
{
    public function send(Notification $notification, NotificationDispatch $dispatch): void
    {
        // Intentionally empty — see class docblock.
    }
}
