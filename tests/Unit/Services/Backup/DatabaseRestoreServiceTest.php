<?php

declare(strict_types=1);

use App\Exceptions\DatabaseRestoreFailedException;
use App\Services\Backup\DatabaseRestoreService;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Backup\BackupDestination\Backup;
use ZipArchive;

/*
|--------------------------------------------------------------------------
| Database Restore Mechanics
|--------------------------------------------------------------------------
|
| Exercises DatabaseRestoreService::restore() end to end against a real zip
| archive (built here with ZipArchive, never a mock) sitting on a faked
| backup disk — the same Storage::fake('backups') technique
| tests/Feature/Admin/BackupRestoreTest.php already uses for this exact
| dependency, so find()/restore() resolve the artefact through the real
| Spatie\Backup\BackupDestination\BackupDestination machinery instead of a
| stand-in.
|
| Both `psql` invocations are faked via Process::fake(): a real subprocess
| replaying a schema reset and a dump against this suite's own shared
| 'booking_testing' database would corrupt it for every test that runs
| afterward in the same process. tests/Feature/Operations/
| RestoreRehearsalTest.php is the one place a real replay is deliberately
| rehearsed, against a disposable third database.
|
| Str::createRandomStringsUsing() pins the random suffix
| makeTemporaryDirectory() mixes into its working-directory name, so each
| test can assert directly that its own working directory no longer exists
| once restore() returns — including via the `finally` block on every
| failure path that reaches it.
|
*/

beforeEach(function (): void {
    $this->service = app(DatabaseRestoreService::class);
    $this->diskName = $this->service->diskName();
    $this->backupName = $this->service->backupName();

    Storage::fake($this->diskName);
});

afterEach(function (): void {
    Str::createRandomStringsNormally();
});

/**
 * Builds a real zip archive containing the given `db-dumps/*.sql` entries
 * (relative entry name => file content) and uploads it onto the faked
 * backup disk at a path find()/restore() can resolve — mirrors
 * DatabaseBackupService's own artefact layout without ever invoking
 * pg_dump.
 *
 * @param  array<string, string>  $entries
 */
function seedRestoreArchive(string $diskName, string $backupName, array $entries): string
{
    $zipPath = sys_get_temp_dir().'/database-restore-service-test-'.bin2hex(random_bytes(8)).'.zip';

    $archive = new ZipArchive;
    $archive->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    foreach ($entries as $name => $content) {
        $archive->addFromString($name, $content);
    }

    $archive->close();

    $backupPath = $backupName.'/'.now()->subMinute()->format('Y-m-d-H-i-s').'-'.bin2hex(random_bytes(4)).'.zip';
    Storage::disk($diskName)->put($backupPath, (string) file_get_contents($zipPath));

    @unlink($zipPath);

    return $backupPath;
}

/** @return array<string, mixed> */
function restoreServiceConnectionConfig(): array
{
    /** @var array<string, mixed> $connection */
    $connection = (array) config('database.connections.'.config('database.default'));

    return $connection;
}

/**
 * True when the given `psql` invocation (as recorded by Process::fake())
 * targets exactly the host/port/username/dbname this application's own
 * default database connection is configured with.
 *
 * @param  list<string>  $command
 */
function commandTargetsConfiguredConnection(array $command): bool
{
    $connection = restoreServiceConnectionConfig();

    return $command[0] === 'psql'
        && in_array('--host='.(string) ($connection['host'] ?? ''), $command, true)
        && in_array('--port='.(string) ($connection['port'] ?? ''), $command, true)
        && in_array('--username='.(string) ($connection['username'] ?? ''), $command, true)
        && in_array('--dbname='.(string) ($connection['database'] ?? ''), $command, true)
        && in_array('--no-password', $command, true);
}

/**
 * Forces makeTemporaryDirectory()'s Str::random(12) call to return a known
 * suffix for the duration of the test, and returns the exact working
 * directory path restore() will therefore create — so cleanup can be
 * asserted against a real, predictable path instead of a glob guess.
 */
function pinRestoreWorkingDirectory(string $suffix): string
{
    Str::createRandomStringsUsing(fn (): string => $suffix);

    return sys_get_temp_dir().'/booking-restore-'.$suffix;
}

it('returns null from find() when no backup matches the given path', function (): void {
    seedRestoreArchive($this->diskName, $this->backupName, ['db-dumps/booking.sql' => 'SELECT 1;']);

    expect($this->service->find('does-not-exist/at-all.zip'))->toBeNull();
});

it('returns the matching backup from find() once the artefact exists on the disk', function (): void {
    $path = seedRestoreArchive($this->diskName, $this->backupName, ['db-dumps/booking.sql' => 'SELECT 1;']);
    $otherPath = seedRestoreArchive($this->diskName, $this->backupName, ['db-dumps/other.sql' => 'SELECT 2;']);

    $found = $this->service->find($path);
    $foundOther = $this->service->find($otherPath);

    expect($found)->toBeInstanceOf(Backup::class);
    expect($found->path())->toBe($path)
        ->and($found->exists())->toBeTrue();

    expect($foundOther)->toBeInstanceOf(Backup::class);
    expect($foundOther->path())->toBe($otherPath);
});

it('throws missingArtifact when the requested backup path does not exist on the disk', function (): void {
    $path = 'nonexistent/does-not-exist.zip';
    $workingDirectory = pinRestoreWorkingDirectory('missing-artifact');

    expect(fn () => $this->service->restore($path))
        ->toThrow(
            DatabaseRestoreFailedException::class,
            "Backup [{$path}] on disk [{$this->diskName}] no longer exists — nothing to restore.",
        );

    // The missing-artifact check fails before any working directory is
    // created at all — nothing for the `finally` block to clean up here.
    expect(is_dir($workingDirectory))->toBeFalse();
});

it('throws malformedArchive when the archive holds no db-dumps entry at all', function (): void {
    $path = seedRestoreArchive($this->diskName, $this->backupName, ['readme.txt' => 'not a dump']);
    $workingDirectory = pinRestoreWorkingDirectory('zero-dump-entries');

    expect(fn () => $this->service->restore($path))
        ->toThrow(
            DatabaseRestoreFailedException::class,
            "Backup [{$path}] does not hold exactly one plain-text database dump — refusing to guess which file to restore.",
        );

    expect(is_dir($workingDirectory))->toBeFalse();
});

it('throws malformedArchive when the archive holds more than one db-dumps entry', function (): void {
    $path = seedRestoreArchive($this->diskName, $this->backupName, [
        'db-dumps/first.sql' => 'SELECT 1;',
        'db-dumps/second.sql' => 'SELECT 2;',
    ]);
    $workingDirectory = pinRestoreWorkingDirectory('two-dump-entries');

    expect(fn () => $this->service->restore($path))
        ->toThrow(
            DatabaseRestoreFailedException::class,
            "Backup [{$path}] does not hold exactly one plain-text database dump — refusing to guess which file to restore.",
        );

    expect(is_dir($workingDirectory))->toBeFalse();
});

it('resets the schema and replays the dump through psql, in order, against the configured connection', function (): void {
    Process::fake();

    $path = seedRestoreArchive($this->diskName, $this->backupName, ['db-dumps/booking.sql' => 'SELECT 1;']);
    $workingDirectory = pinRestoreWorkingDirectory('happy-path');

    $this->service->restore($path);

    expect(is_dir($workingDirectory))->toBeFalse();

    Process::assertRanInOrder([
        fn (PendingProcess $process): bool => commandTargetsConfiguredConnection($process->command)
            && in_array('-c', $process->command, true)
            && in_array('DROP SCHEMA IF EXISTS public CASCADE; CREATE SCHEMA public;', $process->command, true),
        fn (PendingProcess $process): bool => commandTargetsConfiguredConnection($process->command)
            && in_array('-v', $process->command, true)
            && in_array('ON_ERROR_STOP=1', $process->command, true)
            && in_array('-f', $process->command, true),
    ]);
});

it('throws processFailed for the schema-reset step when that psql call fails, without ever attempting the replay', function (): void {
    Process::fake(function (PendingProcess $process) {
        if (in_array('-c', $process->command, true)) {
            return Process::result(errorOutput: 'schema reset exploded', exitCode: 1);
        }

        return Process::result(exitCode: 0);
    });

    $path = seedRestoreArchive($this->diskName, $this->backupName, ['db-dumps/booking.sql' => 'SELECT 1;']);
    $workingDirectory = pinRestoreWorkingDirectory('schema-reset-failure');

    expect(fn () => $this->service->restore($path))
        ->toThrow(DatabaseRestoreFailedException::class, 'Restore step [schema reset] failed: schema reset exploded');

    Process::assertRanTimes(fn (PendingProcess $process): bool => in_array('-c', $process->command, true), 1);
    Process::assertNotRan(fn (PendingProcess $process): bool => in_array('-f', $process->command, true));

    expect(is_dir($workingDirectory))->toBeFalse();
});

it('throws processFailed for the replay step when that psql call fails after the schema reset already succeeded', function (): void {
    Process::fake(function (PendingProcess $process) {
        if (in_array('-f', $process->command, true)) {
            return Process::result(errorOutput: 'replay exploded', exitCode: 1);
        }

        return Process::result(exitCode: 0);
    });

    $path = seedRestoreArchive($this->diskName, $this->backupName, ['db-dumps/booking.sql' => 'SELECT 1;']);
    $workingDirectory = pinRestoreWorkingDirectory('replay-failure');

    expect(fn () => $this->service->restore($path))
        ->toThrow(DatabaseRestoreFailedException::class, 'Restore step [dump replay] failed: replay exploded');

    Process::assertRanTimes(fn (PendingProcess $process): bool => in_array('-c', $process->command, true), 1);
    Process::assertRanTimes(fn (PendingProcess $process): bool => in_array('-f', $process->command, true), 1);

    expect(is_dir($workingDirectory))->toBeFalse();
});
