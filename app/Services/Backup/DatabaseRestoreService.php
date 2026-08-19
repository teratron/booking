<?php

declare(strict_types=1);

namespace App\Services\Backup;

use App\Exceptions\DatabaseRestoreFailedException;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Spatie\Backup\BackupDestination\Backup;
use Spatie\Backup\BackupDestination\BackupDestination;
use ZipArchive;

/**
 * Restores the application database from a previously produced backup
 * artefact — the counterpart to {@see DatabaseBackupService::run()}. Always
 * run from the queued restore job, never inline: a restore of this size has
 * no more place inside a web request's own time budget than producing the
 * backup itself does.
 *
 * The artefact is a plain-text `pg_dump` output wrapped in a zip archive —
 * `config/backup.php` configures no custom `pg_dump` format and no
 * compressor, so the archive holds exactly one `db-dumps/*.sql` entry.
 * Restoring means extracting that one file and replaying it through `psql`,
 * the same client image the backup job already carries.
 *
 * A plain `pg_dump` output carries no `DROP`/`--clean` statements of its
 * own, so the destination's `public` schema is dropped and recreated
 * immediately before the dump replays — without that step, restoring into
 * a database that already holds the current schema would fail outright on
 * the very first `CREATE TABLE`. This intentionally does not attempt to
 * terminate other backends holding open connections to the same database
 * first; a production restore is an offline, maintenance-window operation
 * by nature, a constraint the operations runbook records rather than one
 * this service enforces on its own.
 */
final class DatabaseRestoreService
{
    public function __construct(private readonly BackupDestinationDisk $destinationDisk) {}

    public function diskName(): string
    {
        return $this->destinationDisk->name();
    }

    public function backupName(): string
    {
        return (string) config('backup.backup.name');
    }

    /**
     * Resolves a previously produced database backup by the same `path()`
     * identity the administration screen's own history listing already
     * exposes, or null once the artefact no longer exists on the disk.
     */
    public function find(string $backupPath): ?Backup
    {
        return BackupDestination::create($this->diskName(), $this->backupName())
            ->backups()
            ->first(fn (Backup $backup): bool => $backup->path() === $backupPath);
    }

    /**
     * @throws DatabaseRestoreFailedException when the artefact cannot be
     *                                        found, its archive does not hold exactly one SQL dump, or `psql`
     *                                        itself fails at either the schema-reset or replay step
     */
    public function restore(string $backupPath): void
    {
        $backup = $this->find($backupPath);

        if (! $backup instanceof Backup || ! $backup->exists()) {
            throw DatabaseRestoreFailedException::missingArtifact($this->diskName(), $backupPath);
        }

        $workingDirectory = $this->makeTemporaryDirectory();

        try {
            $archivePath = $workingDirectory.'/archive.zip';
            $this->download($backup, $archivePath);

            $dumpPath = $this->extractSingleDump($archivePath, $workingDirectory, $backupPath);

            $this->resetSchema();
            $this->replay($dumpPath, $backupPath);
        } finally {
            $this->cleanUp($workingDirectory);
        }
    }

    private function download(Backup $backup, string $destination): void
    {
        $stream = $backup->stream();
        $handle = fopen($destination, 'wb');

        if ($handle === false) {
            if (is_resource($stream)) {
                fclose($stream);
            }

            throw DatabaseRestoreFailedException::malformedArchive($backup->path());
        }

        stream_copy_to_stream($stream, $handle);
        fclose($handle);

        if (is_resource($stream)) {
            fclose($stream);
        }
    }

    /**
     * Reads the archive's own central directory for exactly one
     * `db-dumps/*.sql` entry — matching {@see DatabaseBackupService}'s own
     * dump layout rather than assuming a fixed filename, since the entry
     * name carries the connection and database name. More or fewer than one
     * match means this is not an artefact this service knows how to
     * restore, and refusing to guess is safer than picking the first match.
     */
    private function extractSingleDump(string $archivePath, string $workingDirectory, string $backupPath): string
    {
        $archive = new ZipArchive;

        if ($archive->open($archivePath) !== true) {
            throw DatabaseRestoreFailedException::malformedArchive($backupPath);
        }

        $dumpEntries = [];

        for ($index = 0; $index < $archive->numFiles; $index++) {
            $name = $archive->getNameIndex($index);

            if (is_string($name) && str_starts_with($name, 'db-dumps/') && str_ends_with($name, '.sql')) {
                $dumpEntries[] = $name;
            }
        }

        if (count($dumpEntries) !== 1) {
            $archive->close();

            throw DatabaseRestoreFailedException::malformedArchive($backupPath);
        }

        $archive->extractTo($workingDirectory, $dumpEntries[0]);
        $archive->close();

        return $workingDirectory.'/'.$dumpEntries[0];
    }

    private function resetSchema(): void
    {
        $result = $this->psql(['-c', 'DROP SCHEMA IF EXISTS public CASCADE; CREATE SCHEMA public;']);

        if (! $result->successful()) {
            throw DatabaseRestoreFailedException::processFailed('schema reset', $result->errorOutput());
        }
    }

    private function replay(string $dumpPath, string $backupPath): void
    {
        $result = $this->psql(['-v', 'ON_ERROR_STOP=1', '-f', $dumpPath]);

        if (! $result->successful()) {
            throw DatabaseRestoreFailedException::processFailed($backupPath, $result->errorOutput());
        }
    }

    /** @param  list<string>  $arguments */
    private function psql(array $arguments): ProcessResult
    {
        $connection = $this->connectionConfig();

        return Process::timeout(1800)
            ->env(['PGPASSWORD' => (string) ($connection['password'] ?? '')])
            ->run([
                'psql',
                '--host='.(string) ($connection['host'] ?? ''),
                '--port='.(string) ($connection['port'] ?? ''),
                '--username='.(string) ($connection['username'] ?? ''),
                '--dbname='.(string) ($connection['database'] ?? ''),
                '--no-password',
                ...$arguments,
            ]);
    }

    /** @return array<string, mixed> */
    private function connectionConfig(): array
    {
        $connectionName = (string) config('database.default');

        /** @var array<string, mixed> $connection */
        $connection = (array) config("database.connections.{$connectionName}");

        return $connection;
    }

    private function makeTemporaryDirectory(): string
    {
        $path = sys_get_temp_dir().'/booking-restore-'.Str::random(12);
        mkdir($path, 0700, true);

        return $path;
    }

    private function cleanUp(string $workingDirectory): void
    {
        if (! is_dir($workingDirectory)) {
            return;
        }

        $entries = scandir($workingDirectory);

        if ($entries !== false) {
            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }

                $entryPath = $workingDirectory.'/'.$entry;

                if (is_dir($entryPath)) {
                    $this->cleanUp($entryPath);

                    continue;
                }

                @unlink($entryPath);
            }
        }

        @rmdir($workingDirectory);
    }
}
