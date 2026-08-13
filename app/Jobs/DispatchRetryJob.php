<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\NotificationDispatch;
use App\Services\Settings\SettingsRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * The scheduled resurrection pass behind delivery retry — dispatched by the
 * scheduler (see routes/console.php), never reachable from a web route.
 *
 * DispatchNotificationJob already retries a single delivery attempt cycle
 * up to its own `$tries`, backing off in seconds — machinery aimed at
 * transient trouble within that cycle. This job covers what that machinery
 * cannot: a channel unavailable for longer than one cycle, which leaves the
 * dispatch permanently `failed`. It gives such a dispatch a further attempt
 * cycle, up to `notifications.dispatch_max_retries` sweep-level attempts —
 * a budget tracked on `retry_count`, deliberately separate from
 * DispatchNotificationJob's own `$tries` so the two limits never collide.
 *
 * Only ever selects `failed` rows with retries remaining: `queued` and
 * `suppressed` dispatches are mid-flight or deliberately skipped, and
 * `sent` is final — none of the three are touched. A dispatch whose retry
 * budget is exhausted is left `failed` permanently, which is the correct,
 * visible end state for an administrator to act on.
 */
final class DispatchRetryJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(SettingsRepository $settings): void
    {
        $maxRetries = (int) $settings->get('notifications.dispatch_max_retries');

        NotificationDispatch::query()
            ->where('status', 'failed')
            ->where('retry_count', '<', $maxRetries)
            ->chunkById(200, function (Collection $dispatches): void {
                /** @var NotificationDispatch $dispatch */
                foreach ($dispatches as $dispatch) {
                    // Atomic increment-plus-status-write: claiming a
                    // dispatch for retry and taking it out of this same
                    // query's result set must happen together, or a retry
                    // cycle that outlives this job's own run (backoff can
                    // span minutes) would let the next scheduled run
                    // re-select and re-queue the same dispatch again.
                    $dispatch->increment('retry_count', 1, ['status' => 'queued']);

                    DispatchNotificationJob::dispatch($dispatch->id);
                }
            });
    }
}
