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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * The scheduled reminder behind availability staleness — dispatched by the
 * scheduler (see routes/console.php), never reachable from a web route.
 * Raises a `confirm_availability_status` notification, through the shared
 * dispatch pipeline, for every object of a type that tracks availability
 * (`object_types.has_availability_status`) whose status has not been
 * reconfirmed within `availability.confirmation_period_days` — the same
 * setting and the same `availability_last_confirmed_at` threshold
 * SweepStaleAvailabilityJob already reads for its own, unrelated
 * auto-reset pass; this job only ever reminds, it never mutates the status.
 *
 * Registered daily for the same reason StalenessSweepJob is: the schedule
 * only needs to run more often than the cadence it enforces, with the
 * actual "once per cadence" guarantee coming from the idempotency check
 * below rather than the schedule interval itself.
 */
final class AvailabilityConfirmationSweepJob implements ShouldQueue
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
        $type = NotificationType::query()->where('key', 'confirm_availability_status')->first();

        if (! $type instanceof NotificationType) {
            return;
        }

        $cadenceDays = (int) $settings->get('availability.confirmation_period_days');
        $threshold = now()->subDays($cadenceDays);

        Object_::query()
            ->withUnmoderated()
            ->whereNotNull('owner_id')
            ->whereHas('objectType', fn (Builder $query) => $query->where('has_availability_status', true))
            ->where(function (Builder $query) use ($threshold): void {
                $query->whereNull('availability_last_confirmed_at')
                    ->orWhere('availability_last_confirmed_at', '<', $threshold);
            })
            ->chunkById(200, function (Collection $objects) use ($dispatcher, $type, $cadenceDays): void {
                /** @var Object_ $object */
                foreach ($objects as $object) {
                    if ($dispatcher->raisedWithin($object, $type, $cadenceDays)) {
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
