<?php

declare(strict_types=1);

use App\Services\Backup\BackupDestinationDisk;
use App\Services\Backup\BackupIntegrityService;
use App\Services\Backup\MediaBackupService;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Storage;
use Spatie\Backup\BackupDestination\Backup;
use Spatie\Backup\BackupDestination\BackupCollection;
use Spatie\Backup\Tasks\Cleanup\CleanupStrategy;

/*
|--------------------------------------------------------------------------
| Scheduled Off-Server Backups
|--------------------------------------------------------------------------
|
| Database backups run daily; media backs up on its own, slower schedule.
| Both write to a destination disk that is neither the application server
| nor the media bucket they protect. Several generations are retained, and
| every artefact is integrity-verified rather than assumed sound.
|
*/

it('registers the database backup daily and the media backup on its own weekly cadence, both dispatched as queued jobs', function (): void {
    $schedule = app(Schedule::class);
    $events = collect($schedule->events())->keyBy(fn ($event) => $event->description ?? '');

    expect($events->has('backup:database'))->toBeTrue()
        ->and($events->get('backup:database')->expression)->toBe('0 0 * * *')
        ->and($events->has('backup:media'))->toBeTrue()
        ->and($events->get('backup:media')->expression)->toBe('0 0 * * 0')
        ->and($events->has('backup:cleanup'))->toBeTrue()
        ->and($events->has('backup:monitor'))->toBeTrue();

    // Job-backed schedule entries — the same shape every other scheduled
    // job in this codebase uses (see routes/console.php) — dispatch a
    // callback rather than an artisan command, so the job class itself is
    // only reachable through the queue the scheduler feeds, never through
    // any registered route.
    $databaseEvent = $events->get('backup:database');
    $mediaEvent = $events->get('backup:media');

    expect($databaseEvent)->toBeInstanceOf(CallbackEvent::class)
        ->and($mediaEvent)->toBeInstanceOf(CallbackEvent::class);
});

it('points the backup destination at a disk that is neither the application disk nor the media disk', function (): void {
    $destinationDisks = (array) config('backup.backup.destination.disks');

    expect($destinationDisks)->not->toContain('local')
        ->and($destinationDisks)->not->toContain('s3')
        ->and(app(BackupDestinationDisk::class)->name())->toBe('backups');

    // The disk configuration itself resolves to a bucket distinct from the
    // media bucket it protects — never the same bucket under a second name.
    expect(config('filesystems.disks.backups.bucket'))
        ->not->toBe(config('filesystems.disks.s3.bucket'))
        ->and(config('filesystems.disks.backups.bucket'))->not->toBeEmpty();
});

it('keeps the configured number of database backup generations and prunes the rest', function (): void {
    $keep = (int) config('booking.backups.database_generations_to_keep');
    $backupName = (string) config('backup.backup.name');

    $disk = Storage::fake('backups');

    $paths = [];

    foreach (range(0, $keep + 2) as $daysAgo) {
        $timestamp = now()->subDays($daysAgo)->format('Y-m-d-H-i-s');
        $path = "{$backupName}/{$timestamp}.zip";
        $disk->put($path, 'placeholder-zip-bytes');
        $paths[] = $path;
    }

    $backups = BackupCollection::createFromFiles($disk, $paths);

    app(CleanupStrategy::class)->deleteOldBackups($backups);

    expect($disk->allFiles($backupName))->toHaveCount($keep);

    // The newest generations are the ones kept, not an arbitrary subset.
    $remaining = collect($disk->allFiles($backupName))->sort()->values();
    $expectedNewest = collect($paths)->sort()->reverse()->take($keep)->sort()->values();

    expect($remaining->all())->toBe($expectedNewest->all());
});

it('keeps the configured number of media backup generations and prunes the rest', function (): void {
    $keep = (int) config('booking.backups.media_generations_to_keep');

    $disk = Storage::fake('backups');

    $generations = [];

    foreach (range(0, $keep + 2) as $daysAgo) {
        $generation = 'media/'.now()->subDays($daysAgo)->format('Y-m-d_His');
        $disk->put("{$generation}/photo.jpg", 'bytes');
        $generations[] = $generation;
    }

    $pruned = app(MediaBackupService::class)->pruneGenerations($keep);

    expect($pruned)->toBe(count($generations) - $keep)
        ->and($disk->directories('media'))->toHaveCount($keep);
});

it('fails the integrity check for a deliberately corrupted artefact and passes for an intact one', function (): void {
    $disk = Storage::fake('backups');

    $temporaryPath = tempnam(sys_get_temp_dir(), 'booking-test-zip-');
    $archive = new ZipArchive;
    $archive->open($temporaryPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $archive->addFromString('dump.sql', 'SELECT 1;');
    $archive->close();
    $validBytes = (string) file_get_contents($temporaryPath);
    unlink($temporaryPath);

    $disk->put('intact.zip', $validBytes);
    // Truncated mid-archive: a real backup artefact damaged in transit or at
    // rest, not merely an empty or non-zip file — the central directory
    // this cuts off is exactly what ZipArchive::CHECKCONS is built to catch.
    $disk->put('corrupted.zip', substr($validBytes, 0, (int) (strlen($validBytes) * 0.5)));

    $integrity = app(BackupIntegrityService::class);

    expect($integrity->verify(new Backup($disk, 'intact.zip')))->toBeTrue()
        ->and($integrity->verify(new Backup($disk, 'corrupted.zip')))->toBeFalse()
        ->and($integrity->verify(new Backup($disk, 'missing.zip')))->toBeFalse();
});
