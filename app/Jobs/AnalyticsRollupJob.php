<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Compacts one day's raw `stat_events` rows into their `stat_dailies`
 * aggregate. Scheduled daily against yesterday (see `routes/console.php`);
 * never reachable from a web route.
 *
 * Delete-then-reinsert per day inside one transaction, not an upsert:
 * `stat_dailies`' unique index includes several nullable columns, and
 * Postgres treats two NULLs in a unique index as distinct from each other,
 * so `ON CONFLICT` would silently fail to catch the exact rows a re-run
 * needs to replace. Wrapping the whole replace in a transaction is what
 * makes a mid-run failure leave no partial aggregate for the day, rather
 * than half a day's rows next to the truth.
 */
final class AnalyticsRollupJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  ?string  $date  Y-m-d target date; defaults to yesterday when
     *                         omitted, since today's events are still arriving when this normally
     *                         runs
     */
    public function __construct(private readonly ?string $date = null) {}

    public function handle(): void
    {
        $date = $this->date ?? now()->subDay()->toDateString();
        $start = Carbon::parse($date)->startOfDay();
        $end = $start->clone()->addDay();

        DB::transaction(function () use ($date, $start, $end): void {
            DB::table('stat_dailies')->where('date', $date)->delete();

            /** @var Collection<int, object{subject_type: string, subject_id: int, kind: string, contact_channel_type_id: ?int, territory_id: ?int, country_id: ?int, locale: ?string, aggregate_count: int}> $rows */
            $rows = DB::table('stat_events')
                ->selectRaw(
                    'subject_type, subject_id, kind, contact_channel_type_id, territory_id, '
                    .'max(country_id) as country_id, locale, count(*) as aggregate_count'
                )
                ->where('occurred_at', '>=', $start)
                ->where('occurred_at', '<', $end)
                ->groupBy('subject_type', 'subject_id', 'kind', 'contact_channel_type_id', 'territory_id', 'locale')
                ->get();

            $now = now();

            foreach ($rows->chunk(500) as $chunk) {
                DB::table('stat_dailies')->insert(
                    $chunk->map(fn (object $row): array => [
                        'date' => $date,
                        'subject_type' => $row->subject_type,
                        'subject_id' => $row->subject_id,
                        'kind' => $row->kind,
                        'contact_channel_type_id' => $row->contact_channel_type_id,
                        'territory_id' => $row->territory_id,
                        'country_id' => $row->country_id,
                        'locale' => $row->locale,
                        'count' => $row->aggregate_count,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all()
                );
            }
        });
    }
}
