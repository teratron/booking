<?php

declare(strict_types=1);

namespace App\Services\Backup;

use Illuminate\Support\Arr;

/**
 * Resolves the single filesystem disk both the database and media backups
 * write to — read once from Spatie's own destination config so the two
 * backup services can never drift onto different disks from each other.
 *
 * That disk is never the application's default disk and never the media
 * disk: see the `backups` disk's own docblock in config/filesystems.php for
 * why a backup living beside what it protects is not a backup.
 */
final class BackupDestinationDisk
{
    public function name(): string
    {
        /** @var string $disk */
        $disk = Arr::first((array) config('backup.backup.destination.disks', []));

        return $disk;
    }
}
