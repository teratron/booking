<?php

declare(strict_types=1);

namespace App\Services\Backup;

use Spatie\Backup\BackupDestination\Backup;
use Spatie\Backup\BackupDestination\BackupCollection;
use Spatie\Backup\Tasks\Cleanup\CleanupStrategy;

/**
 * Keeps a fixed number of the most recent database backups and deletes
 * every one beyond it — a deliberately simpler replacement for Spatie's own
 * `DefaultStrategy`, whose day/week/month/year tiering does not reduce to a
 * single, administrator-legible "N generations retained" figure the way the
 * specification's own retention requirement states it.
 */
final class GenerationCountCleanupStrategy extends CleanupStrategy
{
    public function deleteOldBackups(BackupCollection $backups): void
    {
        $keep = (int) config('booking.backups.database_generations_to_keep', 7);

        $backups
            ->sortByDesc(fn (Backup $backup) => $backup->date()->timestamp)
            ->values()
            ->slice(max($keep, 0))
            ->each(fn (Backup $backup) => $backup->delete());
    }
}
