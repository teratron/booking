<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when an administrator-triggered database restore cannot complete —
 * the artefact named is missing, its archive does not hold exactly one SQL
 * dump, or the `psql` replay itself failed partway through. The caller is
 * responsible for recording the outcome (journal entry, notification); this
 * exception only carries enough detail to write both.
 */
final class DatabaseRestoreFailedException extends RuntimeException
{
    public static function missingArtifact(string $diskName, string $backupPath): self
    {
        return new self("Backup [{$backupPath}] on disk [{$diskName}] no longer exists — nothing to restore.");
    }

    public static function malformedArchive(string $backupPath): self
    {
        return new self("Backup [{$backupPath}] does not hold exactly one plain-text database dump — refusing to guess which file to restore.");
    }

    public static function processFailed(string $stage, string $errorOutput): self
    {
        $trimmed = trim($errorOutput);

        return new self("Restore step [{$stage}] failed".($trimmed === '' ? '.' : ": {$trimmed}"));
    }
}
