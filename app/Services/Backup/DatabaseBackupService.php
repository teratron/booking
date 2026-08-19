<?php

declare(strict_types=1);

namespace App\Services\Backup;

use App\Exceptions\BackupIntegrityFailedException;
use Illuminate\Support\Facades\Artisan;
use Spatie\Backup\BackupDestination\Backup;
use Spatie\Backup\BackupDestination\BackupDestination;

/**
 * Runs the scheduled database-only backup and verifies the resulting
 * artefact before returning — the seam both the daily schedule and any
 * future manual "run now" action share, so neither path can skip
 * verification by construction.
 */
final class DatabaseBackupService
{
    public function __construct(
        private readonly BackupIntegrityService $integrity,
        private readonly BackupDestinationDisk $destinationDisk,
    ) {}

    /**
     * Produces a fresh database dump on the configured backup destination
     * disk and confirms it opens as a consistent zip archive.
     *
     * @throws BackupIntegrityFailedException when no artefact results, or
     *                                        the one written fails its own integrity check.
     */
    public function run(): Backup
    {
        Artisan::call('backup:run', [
            '--only-db' => true,
            '--disable-notifications' => true,
        ]);

        $backup = $this->latest();

        if (! $this->integrity->verify($backup)) {
            throw BackupIntegrityFailedException::database($this->diskName(), $this->backupName());
        }

        /** @var Backup $backup */
        return $backup;
    }

    /**
     * The most recent database backup on the configured destination disk, or
     * null when none has been produced yet.
     */
    public function latest(): ?Backup
    {
        return BackupDestination::create($this->diskName(), $this->backupName())
            ->backups()
            ->newest();
    }

    public function diskName(): string
    {
        return $this->destinationDisk->name();
    }

    public function backupName(): string
    {
        return (string) config('backup.backup.name');
    }
}
