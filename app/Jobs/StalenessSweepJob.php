<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\NotificationType;
use App\Models\Object_;
use App\Models\User;
use App\Services\Notifications\NotificationDispatchService;
use App\Services\Settings\SettingsRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * The scheduled sweep behind catalog freshness — dispatched by the
 * scheduler (see routes/console.php), never reachable from a web route.
 * Raises an `information_out_of_date` notification, through the shared
 * dispatch pipeline, for every object whose row has not been written to
 * within `moderation.stale_object_days` — the same setting a back-office
 * staleness filter would read, reused rather than duplicated under a
 * second key.
 *
 * Registered daily even though the staleness period itself typically spans
 * months: the schedule only needs to run more often than the window it
 * enforces, and the actual "don't repeat" guarantee comes from the
 * idempotency check below, not the schedule interval — the same division
 * of labour PlacementExpirySweepJob already uses for its own, differently
 * shaped warning schedule.
 *
 * Idempotent per staleness window, not per calendar day: an object that
 * stays untouched keeps matching "not updated recently" on every
 * subsequent run, so gating on "already notified today" would nag the
 * owner daily for as long as they ignore it. Gating on "already notified
 * within the last `stale_object_days`" instead means one reminder per
 * staleness period — the object only becomes eligible again once its own
 * `updated_at` moves, or the window elapses.
 */
final class StalenessSweepJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** A daily lifecycle sweep — see config/horizon.php's own "default" supervisor. */
    public function __construct()
    {
        $this->onQueue('default');
    }

    public function handle(NotificationDispatchService $dispatcher, SettingsRepository $settings): void
    {
        $type = NotificationType::query()->where('key', 'information_out_of_date')->first();

        if (! $type instanceof NotificationType) {
            return;
        }

        $staleDays = (int) $settings->get('moderation.stale_object_days');
        $threshold = now()->subDays($staleDays);

        Object_::query()
            ->withUnmoderated()
            ->whereNotNull('owner_id')
            ->where('updated_at', '<', $threshold)
            ->chunkById(200, function (Collection $objects) use ($dispatcher, $type, $staleDays): void {
                /** @var Object_ $object */
                foreach ($objects as $object) {
                    if ($dispatcher->raisedWithin($object, $type, $staleDays)) {
                        continue;
                    }

                    $recipient = User::query()->find($object->owner_id);

                    if ($recipient instanceof User) {
                        $dispatcher->create($type, $recipient, $object);
                    }
                }
            });
    }
}
