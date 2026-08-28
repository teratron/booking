<?php

declare(strict_types=1);

use App\Exceptions\BackupIntegrityFailedException;
use App\Jobs\DatabaseBackupJob;
use App\Jobs\MediaBackupJob;
use App\Services\Backup\DatabaseBackupService;
use App\Services\Backup\MediaBackupService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/*
|--------------------------------------------------------------------------
| Backup Jobs — Queue Assignment, Delegation, and Failure Propagation
|--------------------------------------------------------------------------
|
| DatabaseBackupService and MediaBackupService are both declared `final`
| (this project's own service-layer convention), so neither can be replaced
| with a strictly-typed Mockery double for handle()'s own parameter — the
| same reason BackupJobServicesTest exercises them for real with only their
| I/O (Artisan, Storage) faked. This suite follows the identical shape: real
| service instances, real handle() calls, and assertions aimed squarely at
| what the JOB adds on top of a one-line delegation — queue assignment,
| call order, the retention count's cast/default, and whether a failure is
| ever swallowed. The services' own internals (dump verification, mirror
| integrity) are proven there, not re-proven here.
|
*/

/** A minimal, structurally valid zip — enough for BackupIntegrityService's own consistency check to pass. */
function backupJobsTestValidZipBytes(): string
{
    $temporaryPath = tempnam(sys_get_temp_dir(), 'booking-jobs-test-zip-');
    $archive = new ZipArchive;
    $archive->open($temporaryPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $archive->addFromString('dump.sql', 'SELECT 1;');
    $archive->close();
    $bytes = (string) file_get_contents($temporaryPath);
    unlink($temporaryPath);

    return $bytes;
}

it('assigns DatabaseBackupJob to the dedicated backups queue', function (): void {
    expect((new DatabaseBackupJob)->queue)->toBe('backups');
});

it('assigns MediaBackupJob to the same dedicated backups queue as the database job', function (): void {
    expect((new MediaBackupJob)->queue)->toBe('backups');
});

it('delegates DatabaseBackupJob::handle() to the injected service without wrapping or altering its outcome', function (): void {
    $service = app(DatabaseBackupService::class);
    $disk = Storage::fake($service->diskName());
    $path = $service->backupName().'/'.now()->format('Y-m-d-H-i-s').'.zip';

    Artisan::shouldReceive('call')
        ->once()
        ->with('backup:run', ['--only-db' => true, '--disable-notifications' => true])
        ->andReturnUsing(function () use ($disk, $path): int {
            $disk->put($path, backupJobsTestValidZipBytes());

            return 0;
        });

    (new DatabaseBackupJob)->handle($service);

    expect($disk->exists($path))->toBeTrue();
});

it('lets a DatabaseBackupService integrity failure propagate out of DatabaseBackupJob::handle() uncaught', function (): void {
    $service = app(DatabaseBackupService::class);
    Storage::fake($service->diskName());

    Artisan::shouldReceive('call')
        ->once()
        ->with('backup:run', ['--only-db' => true, '--disable-notifications' => true])
        // A failed underlying pg_dump process leaves no artefact behind —
        // proving DatabaseBackupJob has no try/catch of its own that would
        // otherwise turn this into a silently "completed" job.
        ->andReturn(1);

    expect(fn () => (new DatabaseBackupJob)->handle($service))->toThrow(
        BackupIntegrityFailedException::class,
        "Database backup on disk [{$service->diskName()}] under [{$service->backupName()}] failed integrity verification after writing.",
    );
});

it('runs MediaBackupJob::handle() by mirroring first, then pruning down to the configured retention count', function (): void {
    config(['booking.backups.media_generations_to_keep' => 2]);

    $frozen = now()->startOfDay();
    $this->travelTo($frozen);

    $service = app(MediaBackupService::class);
    $source = Storage::fake($service->sourceDiskName());
    $destination = Storage::fake($service->destinationDiskName());

    $source->put('objects/1/cover.jpg', 'cover-bytes');

    // Three stale generations, all older than the one this run will
    // create. If pruning ran before mirroring, the brand-new generation
    // would not yet exist to be counted among the two kept, and the
    // assertion below would retain the wrong two.
    $destination->put('media/2020-01-01_000000/old.jpg', 'old-1');
    $destination->put('media/2020-01-02_000000/old.jpg', 'old-2');
    $destination->put('media/2020-01-03_000000/old.jpg', 'old-3');

    (new MediaBackupJob)->handle($service);

    $remaining = collect($destination->directories('media'))->sort()->values()->all();

    expect($remaining)->toBe([
        'media/2020-01-03_000000',
        'media/'.$frozen->format('Y-m-d_His'),
    ]);
});

it('casts a string-valued retention config into an integer before the strictly-typed pruneGenerations() call', function (): void {
    config(['booking.backups.media_generations_to_keep' => '1']);

    $frozen = now()->startOfDay();
    $this->travelTo($frozen);

    $service = app(MediaBackupService::class);
    $source = Storage::fake($service->sourceDiskName());
    $destination = Storage::fake($service->destinationDiskName());

    $source->put('objects/1/cover.jpg', 'cover-bytes');

    $destination->put('media/2020-01-01_000000/old.jpg', 'old-1');
    $destination->put('media/2020-01-02_000000/old.jpg', 'old-2');

    // pruneGenerations(int $keep) is strictly typed — if the job passed the
    // raw string straight through, this call would throw a TypeError before
    // ever reaching the assertion, not merely keep the wrong count.
    (new MediaBackupJob)->handle($service);

    expect($destination->directories('media'))->toBe(['media/'.$frozen->format('Y-m-d_His')]);
});

it('falls back to five kept generations when the retention config is entirely absent', function (): void {
    $backups = (array) config('booking.backups');
    unset($backups['media_generations_to_keep']);
    config(['booking.backups' => $backups]);

    $frozen = now()->startOfDay();
    $this->travelTo($frozen);

    $service = app(MediaBackupService::class);
    $source = Storage::fake($service->sourceDiskName());
    $destination = Storage::fake($service->destinationDiskName());

    $source->put('objects/1/cover.jpg', 'cover-bytes');

    foreach (range(1, 6) as $day) {
        $destination->put(sprintf('media/2020-01-%02d_000000/old.jpg', $day), "old-{$day}");
    }

    (new MediaBackupJob)->handle($service);

    // Six stale generations plus the freshly mirrored one make seven; the
    // job's own default of five is what should survive, not an accidental
    // "keep everything" from a missing config key resolving to null.
    expect($destination->directories('media'))->toHaveCount(5);
});

it('never runs pruneGenerations() when the mirroring run itself fails integrity verification', function (): void {
    $frozen = now()->startOfDay();
    $this->travelTo($frozen);

    $service = app(MediaBackupService::class);
    Storage::fake($service->sourceDiskName());
    $destination = Storage::fake($service->destinationDiskName());

    $generation = 'media/'.$frozen->format('Y-m-d_His');
    // A stray leftover already under the generation this run will produce
    // makes the destination's post-copy count diverge from the (empty)
    // source's own count — the exact mismatch run() exists to catch.
    $destination->put("{$generation}/stray-leftover.txt", 'leftover-from-a-previous-partial-run');

    // A pre-existing stale generation pruneGenerations() would delete if it
    // ever ran — its survival is what proves pruning never fired.
    $destination->put('media/2020-01-01_000000/old.jpg', 'old-1');

    expect(fn () => (new MediaBackupJob)->handle($service))->toThrow(
        BackupIntegrityFailedException::class,
        "Media backup generation [{$generation}] on disk [{$service->destinationDiskName()}] failed integrity verification after writing — the file count copied does not match the source.",
    );

    expect($destination->exists('media/2020-01-01_000000/old.jpg'))->toBeTrue();
});
