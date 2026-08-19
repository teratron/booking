<?php

declare(strict_types=1);

namespace App\Services\Backup\HealthChecks;

use App\Services\Backup\BackupIntegrityService;
use Spatie\Backup\BackupDestination\Backup;
use Spatie\Backup\BackupDestination\BackupDestination;
use Spatie\Backup\Tasks\Monitor\HealthCheck;

/**
 * Extends Spatie's own monitor with the one check its built-in health
 * checks do not cover: that the newest artefact on the destination is a
 * structurally sound archive, not merely present and recent.
 * `MaximumAgeInDays`/`MaximumStorageInMegabytes` (configured alongside this
 * one in config/backup.php) catch a destination that stopped receiving
 * backups; this one catches a destination that received a corrupted one.
 */
final class BackupArchiveIntegrityHealthCheck extends HealthCheck
{
    public function __construct(private readonly BackupIntegrityService $integrity) {}

    public function checkHealth(BackupDestination $backupDestination): void
    {
        $newest = $backupDestination->backups()->newest();

        $this->failIf($newest === null, 'No backup artefact exists yet to verify.');

        /** @var Backup $newest */
        $this->failUnless(
            $this->integrity->verify($newest),
            'The newest backup artefact failed its integrity check.',
        );
    }
}
