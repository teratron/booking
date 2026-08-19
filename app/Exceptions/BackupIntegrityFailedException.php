<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when a backup artefact fails integrity verification immediately
 * after it was written — a corrupted or missing artefact must fail its
 * producing job outright rather than be recorded as a successful backup.
 */
final class BackupIntegrityFailedException extends RuntimeException
{
    public static function database(string $diskName, string $backupName): self
    {
        return new self("Database backup on disk [{$diskName}] under [{$backupName}] failed integrity verification after writing.");
    }

    public static function media(string $diskName, string $generationPath): self
    {
        return new self("Media backup generation [{$generationPath}] on disk [{$diskName}] failed integrity verification after writing — the file count copied does not match the source.");
    }
}
