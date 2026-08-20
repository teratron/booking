<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Object_;
use App\Services\Objects\AvailabilityAdministrationService;
use App\Services\Settings\SettingsRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * The scheduled sweep behind optional auto-reset — dispatched by the
 * scheduler (see routes/console.php), never reachable from a web route.
 * A no-op whenever `availability.auto_reset_enabled` is off, which is the
 * setting's own default: resetting a stale `unavailable` back to
 * `available` manufactures exactly the false vacancy claim this whole
 * feature exists to prevent, so the sweep only ever acts when an
 * administrator has deliberately turned it on for the portal.
 */
final class SweepStaleAvailabilityJob implements ShouldQueue
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

    public function handle(AvailabilityAdministrationService $availability, SettingsRepository $settings): void
    {
        if ($settings->get('availability.auto_reset_enabled') !== true) {
            return;
        }

        $cadenceDays = (int) $settings->get('availability.confirmation_period_days');
        $threshold = now()->subDays($cadenceDays);

        Object_::query()
            ->withUnmoderated()
            ->where('availability_status', 'unavailable')
            ->where(function (Builder $query) use ($threshold): void {
                $query->whereNull('availability_last_confirmed_at')
                    ->orWhere('availability_last_confirmed_at', '<', $threshold);
            })
            ->chunkById(200, function (Collection $objects) use ($availability): void {
                /** @var Object_ $object */
                foreach ($objects as $object) {
                    $availability->autoReset($object);
                }
            });
    }
}
