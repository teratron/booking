<?php

declare(strict_types=1);

namespace App\Services\Backup;

use Spatie\Backup\BackupDestination\Backup;
use ZipArchive;

/**
 * Verifies that a backup artefact already written to its destination disk is
 * a structurally sound zip archive, rather than assuming a successful upload
 * implies a usable file.
 *
 * This is deliberately independent of Spatie's own `verify_backup` option
 * (which only checks the local, pre-upload temporary file for a non-zero,
 * expected file count): this check re-reads the artefact from the
 * destination it was actually written to, and inspects the zip's own
 * internal consistency (central directory, local file headers) rather than
 * merely its file count — the shape of check that catches an artefact
 * truncated or bit-flipped in transit or at rest, not only one that failed
 * to zip in the first place.
 */
final class BackupIntegrityService
{
    /**
     * Downloads the given backup to a temporary local file and verifies it
     * opens as a structurally consistent zip archive.
     *
     * Returns false — never throws — for a missing artefact, an unreadable
     * stream, or a zip that fails `ZipArchive`'s own consistency check
     * (`ZipArchive::CHECKCONS`), which is the built-in detector for a
     * corrupted central directory or truncated entry.
     */
    public function verify(?Backup $backup): bool
    {
        if (! $backup instanceof Backup || ! $backup->exists()) {
            return false;
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'booking-backup-verify-');

        if ($temporaryPath === false) {
            return false;
        }

        try {
            $stream = $backup->stream();
            $handle = fopen($temporaryPath, 'wb');

            if ($handle === false) {
                return false;
            }

            stream_copy_to_stream($stream, $handle);
            fclose($handle);

            if (is_resource($stream)) {
                fclose($stream);
            }

            return $this->isConsistentZip($temporaryPath);
        } finally {
            @unlink($temporaryPath);
        }
    }

    private function isConsistentZip(string $path): bool
    {
        $archive = new ZipArchive;
        $result = $archive->open($path, ZipArchive::CHECKCONS);

        if ($result === true) {
            $valid = $archive->numFiles > 0;
            $archive->close();

            return $valid;
        }

        return false;
    }
}
