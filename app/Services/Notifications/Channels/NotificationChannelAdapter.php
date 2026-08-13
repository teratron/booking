<?php

declare(strict_types=1);

namespace App\Services\Notifications\Channels;

use App\Models\Notification;
use App\Models\NotificationDispatch;

/**
 * The seam that makes adding a channel a registry entry plus a class, never
 * a change to `Notification`, `NotificationDispatch`, or the dispatch
 * pipeline that calls this contract. `$dispatch` is already persisted with
 * status `queued` when an adapter's `send()` runs; the adapter's only job is
 * to attempt delivery and report the outcome — the pipeline owns retry,
 * backoff, and the status transition itself.
 */
interface NotificationChannelAdapter
{
    /**
     * Attempts delivery of $notification's already-rendered title/body
     * through this channel. A failure is reported by throwing, never by a
     * silent return — the pipeline catches it to record `failed` and
     * schedule a retry.
     *
     * @throws \Throwable on any delivery failure
     */
    public function send(Notification $notification, NotificationDispatch $dispatch): void;
}
