<?php

declare(strict_types=1);

use App\Exceptions\BackupIntegrityFailedException;
use App\Services\Backup\DatabaseBackupService;
use App\Services\Backup\MediaBackupService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Spatie\Backup\BackupDestination\Backup;
use ZipArchive;

/*
|--------------------------------------------------------------------------
| Scheduled Backup Producers — Dispatch and Verification Logic
|--------------------------------------------------------------------------
|
| DatabaseBackupService::run() and MediaBackupService::run() share the same
| shape: dispatch the underlying work, then refuse to report success unless
| the resulting artefact actually verifies. The real `backup:run` Artisan
| command is never allowed to execute here — it would shell out to
| `pg_dump` against a live database, which belongs to the dedicated 'slow'
| restore-rehearsal test, not a fast unit test — so `Artisan::call` is
| stubbed to stand in for exactly what that command's own side effect is:
| an artefact appearing on the destination disk. MediaBackupService has no
| external process to stub at all — its "run" is a plain Storage-to-Storage
| copy, so its own disks are simply faked.
|
*/

/** A minimal, structurally valid zip — the same fixture BackupScheduleTest builds for its own integrity assertions. */
function backupJobValidZipBytes(): string
{
    $temporaryPath = tempnam(sys_get_temp_dir(), 'booking-test-zip-');
    $archive = new ZipArchive;
    $archive->open($temporaryPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $archive->addFromString('dump.sql', 'SELECT 1;');
    $archive->close();
    $bytes = (string) file_get_contents($temporaryPath);
    unlink($temporaryPath);

    return $bytes;
}

it('dispatches backup:run with the only-db and disabled-notifications flags, then returns the freshly written backup once it verifies', function (): void {
    $service = app(DatabaseBackupService::class);
    $disk = Storage::fake($service->diskName());
    $path = $service->backupName().'/'.now()->format('Y-m-d-H-i-s').'.zip';

    Artisan::shouldReceive('call')
        ->once()
        ->with('backup:run', ['--only-db' => true, '--disable-notifications' => true])
        ->andReturnUsing(function () use ($disk, $path): int {
            // Stands in for the real command's own side effect: an artefact
            // landing on the destination disk only after this call runs —
            // proving run() reads the disk after dispatching, not before.
            $disk->put($path, backupJobValidZipBytes());

            return 0;
        });

    $backup = $service->run();

    expect($backup)->toBeInstanceOf(Backup::class)
        ->and($backup->path())->toBe($path);
});

it('throws a database integrity-failure exception naming the disk and backup name when the run produces no artefact at all', function (): void {
    $service = app(DatabaseBackupService::class);
    Storage::fake($service->diskName());

    Artisan::shouldReceive('call')
        ->once()
        ->with('backup:run', ['--only-db' => true, '--disable-notifications' => true])
        // A failed underlying pg_dump process leaves no file behind — the
        // command itself does not throw, so this mirrors its real failure
        // shape rather than an exception escaping Artisan::call.
        ->andReturn(1);

    expect(fn () => $service->run())->toThrow(
        BackupIntegrityFailedException::class,
        "Database backup on disk [{$service->diskName()}] under [{$service->backupName()}] failed integrity verification after writing.",
    );
});

it('throws when the artefact the run produced fails its own zip integrity check', function (): void {
    $service = app(DatabaseBackupService::class);
    $disk = Storage::fake($service->diskName());
    $path = $service->backupName().'/'.now()->format('Y-m-d-H-i-s').'.zip';

    Artisan::shouldReceive('call')
        ->once()
        ->with('backup:run', ['--only-db' => true, '--disable-notifications' => true])
        ->andReturnUsing(function () use ($disk, $path): int {
            $validBytes = backupJobValidZipBytes();
            // Truncated mid-archive: a real artefact damaged in transit or
            // at rest, not merely an empty or non-zip file.
            $disk->put($path, substr($validBytes, 0, (int) (strlen($validBytes) * 0.5)));

            return 0;
        });

    expect(fn () => $service->run())->toThrow(
        BackupIntegrityFailedException::class,
        "Database backup on disk [{$service->diskName()}] under [{$service->backupName()}] failed integrity verification after writing.",
    );
});

it("reads the source disk from Media Library's own config rather than a hardcoded default", function (): void {
    config(['media-library.disk_name' => 'custom-media-disk']);

    expect(app(MediaBackupService::class)->sourceDiskName())->toBe('custom-media-disk');
});

it('mirrors every file on the source media disk into a fresh, timestamped generation directory on the destination disk', function (): void {
    $service = app(MediaBackupService::class);
    $source = Storage::fake($service->sourceDiskName());
    $destination = Storage::fake($service->destinationDiskName());

    $source->put('objects/1/cover.jpg', 'cover-bytes');
    $source->put('objects/1/lobby.jpg', 'lobby-bytes');

    $generation = $service->run();

    expect($generation)->toStartWith('media/');

    $mirrored = $destination->allFiles($generation);

    expect($mirrored)->toHaveCount(2)
        ->and($destination->get("{$generation}/objects/1/cover.jpg"))->toBe('cover-bytes')
        ->and($destination->get("{$generation}/objects/1/lobby.jpg"))->toBe('lobby-bytes');
});

it('returns zero copied files, and a generation directory of its own, for a source disk holding nothing yet', function (): void {
    $service = app(MediaBackupService::class);
    Storage::fake($service->sourceDiskName());
    $destination = Storage::fake($service->destinationDiskName());

    $generation = $service->run();

    expect($destination->allFiles($generation))->toBeEmpty();
});

it('throws a media integrity-failure exception naming the destination disk and generation when the copied count does not match the source', function (): void {
    $frozen = now()->startOfDay();
    $this->travelTo($frozen);

    $service = app(MediaBackupService::class);
    Storage::fake($service->sourceDiskName());
    $destination = Storage::fake($service->destinationDiskName());

    $generation = 'media/'.$frozen->format('Y-m-d_His');
    // A file already sitting under the exact generation this run will
    // produce — as if a previous, partial run left something behind —
    // makes the destination's post-copy count diverge from the (empty)
    // source's own count: the exact mismatch run() exists to catch.
    $destination->put("{$generation}/stray-leftover.txt", 'leftover-from-a-previous-partial-run');

    expect(fn () => $service->run())->toThrow(
        BackupIntegrityFailedException::class,
        "Media backup generation [{$generation}] on disk [{$service->destinationDiskName()}] failed integrity verification after writing — the file count copied does not match the source.",
    );
});
