<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Notification;
use App\Models\NotificationChannel;
use App\Models\NotificationDispatch;
use App\Services\Notifications\NotificationChannelAdapterRegistry;
use App\Services\Notifications\NotificationDispatchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Throwable;

/**
 * One channel's attempt to deliver one already-created `Notification`,
 * queued once per (notification, channel) pair by
 * {@see NotificationDispatchService::send()}.
 *
 * Idempotent by construction: it operates on an existing `NotificationDispatch`
 * row rather than creating one, and a dispatch already `sent` is left alone —
 * running this job twice for the same row never sends a second message. A
 * thrown adapter failure is rethrown after being recorded, so the queue's own
 * retry/backoff machinery decides whether to try again; only `failed()`,
 * which the queue calls once retries are exhausted, marks the dispatch
 * `failed`. Between attempts the row stays `queued` — there is no
 * intermediate "retrying" status, since Laravel's own retry count already
 * tracks that.
 */
final class DispatchNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Total delivery attempts before the dispatch is given up on as failed. */
    public int $tries = 5;

    public function __construct(private readonly int $dispatchId)
    {
        // A recipient is actively waiting on this delivery — see
        // config/horizon.php's own "notifications" supervisor, sized with
        // the shortest wait threshold of any declared queue.
        $this->onQueue('notifications');
    }

    /** @return list<int> seconds to wait before each retry, in order */
    public function backoff(): array
    {
        return [10, 30, 60, 120];
    }

    public function handle(NotificationChannelAdapterRegistry $registry): void
    {
        $dispatch = NotificationDispatch::query()->find($this->dispatchId);

        if (! $dispatch instanceof NotificationDispatch || $dispatch->status === 'sent') {
            // Either the row is gone (nothing left to do) or a prior attempt
            // already delivered it — re-sending is exactly what this guard
            // exists to prevent.
            return;
        }

        $notification = $dispatch->notification;
        $channel = $dispatch->channel;

        if (! $notification instanceof Notification || ! $channel instanceof NotificationChannel) {
            throw new RuntimeException("Dispatch [{$this->dispatchId}] references a missing notification or channel.");
        }

        try {
            $registry->forKey($channel->key)->send($notification, $dispatch);

            $dispatch->status = 'sent';
            $dispatch->attempted_at = now();
            $dispatch->failure_reason = null;
            $dispatch->save();
        } catch (Throwable $e) {
            $dispatch->attempted_at = now();
            $dispatch->failure_reason = $e->getMessage();
            $dispatch->save();

            throw $e;
        }
    }

    /**
     * Called by the queue once every retry has been exhausted. This is the
     * only place a dispatch is ever marked `failed` — a mid-retry exception
     * leaves it `queued` so an administrator reading the table mid-flight
     * sees "still trying", not a false final failure.
     */
    public function failed(Throwable $exception): void
    {
        $dispatch = NotificationDispatch::query()->find($this->dispatchId);

        if ($dispatch instanceof NotificationDispatch && $dispatch->status !== 'sent') {
            $dispatch->status = 'failed';
            $dispatch->failure_reason = $exception->getMessage();
            $dispatch->attempted_at = now();
            $dispatch->save();
        }
    }
}
