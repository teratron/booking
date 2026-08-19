<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Backup\DatabaseBackupService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * The scheduled daily database backup — dispatched by the scheduler (see
 * routes/console.php), never reachable from a web route. Writes to the
 * dedicated backup destination disk (never the application's local disk,
 * never the media disk — see that disk's own docblock in
 * config/filesystems.php) and verifies the resulting artefact before the
 * job is considered successful; a corrupted or missing artefact fails this
 * job outright rather than being recorded as a completed backup.
 */
final class DatabaseBackupJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(DatabaseBackupService $backups): void
    {
        $backups->run();
    }
}
