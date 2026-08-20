<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Settings\SettingsRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Discards raw `stat_events` rows past `analytics.raw_retention_days`,
 * once — and only once — each day's rollup has produced its
 * `stat_dailies` aggregate. Scheduled daily, after {@see AnalyticsRollupJob}
 * (see `routes/console.php`); never reachable from a web route.
 *
 * The `EXISTS` guard against `stat_dailies` is the safety rule made literal:
 * a day past the retention cutoff is only ever deleted once at least one
 * rollup row for that date exists. A day with zero raw events never gets a
 * `stat_dailies` row either, but there is nothing to delete for it in that
 * case, so the guard costs that day nothing.
 */
final class AnalyticsCompactionJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Shares {@see AnalyticsRollupJob}'s "analytics" queue. */
    public function __construct()
    {
        $this->onQueue('analytics');
    }

    public function handle(SettingsRepository $settings): void
    {
        $retentionDays = (int) $settings->get('analytics.raw_retention_days');
        $cutoff = now()->subDays($retentionDays)->startOfDay();

        DB::table('stat_events')
            ->where('occurred_at', '<', $cutoff)
            ->whereExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('stat_dailies')
                    ->whereRaw('stat_dailies.date = stat_events.occurred_at::date');
            })
            ->delete();
    }
}
