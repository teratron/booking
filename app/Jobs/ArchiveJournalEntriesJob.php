<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Settings\SettingsRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OwenIt\Auditing\Models\Audit;

/**
 * Archives journal entries older than `journal.retention_days` — dispatched
 * by the scheduler (see routes/console.php), never reachable from a web
 * route. "Archives", not "deletes": a database trigger enforces that
 * `audits` is never updated or deleted by any role, including this one, so
 * archival here means exporting the aging entries to durable object storage
 * rather than pruning the live table, which the schema does not allow.
 * A row this job has exported remains fully queryable in the panel; the
 * export exists for long-term retention off the hot table, not as a
 * replacement for it.
 */
final class ArchiveJournalEntriesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(SettingsRepository $settings): void
    {
        $retentionDays = (int) $settings->get('journal.retention_days');
        $cutoff = now()->subDays($retentionDays);

        Audit::query()
            ->where('created_at', '<', $cutoff)
            ->orderBy('id')
            ->chunkById(500, function (Collection $entries) use ($cutoff): void {
                $path = sprintf('journal-archive/%s-%s.json', $cutoff->format('Y-m-d'), Str::uuid());

                Storage::disk('s3')->put($path, $entries->toJson());

                Log::channel('stack')->info('Archived journal entries older than the retention cutoff.', [
                    'count' => $entries->count(),
                    'cutoff' => $cutoff->toDateString(),
                    'path' => $path,
                ]);
            });
    }
}
