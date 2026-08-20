<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Backup\MediaBackupService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * The scheduled media backup — dispatched by the scheduler (see
 * routes/console.php), never reachable from a web route, and deliberately
 * on its own, slower cadence than {@see DatabaseBackupJob}: media is
 * bulkier and changes less often than the catalog's own writes, so sharing
 * the database dump's daily rhythm would pay a much larger cost for no
 * matching benefit.
 *
 * Mirrors the media disk into a fresh, timestamped generation on the same
 * backup destination disk the database dump uses, verifies the copy, then
 * prunes generations beyond the configured retention count.
 */
final class MediaBackupJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Shares {@see DatabaseBackupJob}'s "backups" queue — see
     * config/horizon.php's own supervisor for why the two heaviest,
     * slowest jobs in the codebase are isolated onto one dedicated,
     * single-process queue.
     */
    public function __construct()
    {
        $this->onQueue('backups');
    }

    public function handle(MediaBackupService $backups): void
    {
        $backups->run();
        $backups->pruneGenerations((int) config('booking.backups.media_generations_to_keep', 5));
    }
}
