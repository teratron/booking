<?php

declare(strict_types=1);

namespace App\Services\Backup;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use Spatie\Backup\BackupDestination\Backup;
use Spatie\Backup\BackupDestination\BackupDestination;
use Spatie\Backup\Config\Config as SpatieBackupConfig;
use Spatie\Backup\Tasks\Monitor\BackupDestinationStatus;
use Spatie\Backup\Tasks\Monitor\BackupDestinationStatusFactory;

/**
 * Everything the backup administration screen reads. Every date here comes
 * straight from the destination disk's own file listing — {@see Backup::date()}
 * parses it from the artefact's own filename, or falls back to the disk's
 * last-modified time — never from a row this project writes and could fail
 * to keep in sync with what actually landed on the disk.
 *
 * Deliberately read-only: producing a fresh backup is
 * {@see DatabaseBackupService}/{@see MediaBackupService}'s own job, and
 * computing health status here never fires the `HealthyBackupWasFound`/
 * `UnhealthyBackupWasFound` events a real `backup:monitor` run is
 * responsible for — this only inspects the same checks, it does not run
 * the command.
 */
final class BackupAdministrationService
{
    private const string MEDIA_GENERATION_PREFIX = 'media';

    public function __construct(
        private readonly DatabaseBackupService $database,
        private readonly MediaBackupService $media,
    ) {}

    public function lastDatabaseBackup(): ?Backup
    {
        return $this->database->latest();
    }

    /** @return Collection<int, Backup> every retained database backup, newest first */
    public function databaseBackupHistory(int $limit = 20): Collection
    {
        return BackupDestination::create($this->database->diskName(), $this->database->backupName())
            ->backups()
            ->take($limit)
            ->values();
    }

    /**
     * @return Collection<int, array{path: string, date: Carbon}> every retained
     *                                                            media generation, newest first
     */
    public function mediaGenerationHistory(int $limit = 20): Collection
    {
        $disk = Storage::disk($this->media->destinationDiskName());

        return collect($disk->directories(self::MEDIA_GENERATION_PREFIX))
            ->sortByDesc(fn (string $path): string => $path)
            ->take($limit)
            ->map(fn (string $path): array => ['path' => $path, 'date' => $this->mediaGenerationDate($path)])
            ->values();
    }

    public function lastMediaBackupDate(): ?Carbon
    {
        /** @var array{path: string, date: Carbon}|null $newest */
        $newest = $this->mediaGenerationHistory(1)->first();

        return $newest['date'] ?? null;
    }

    /**
     * True once the last database backup is older than
     * `booking.backups.staleness_threshold_hours` — or none exists at all,
     * which is the most stale a portal can be.
     */
    public function isDatabaseBackupStale(): bool
    {
        $last = $this->lastDatabaseBackup();

        if (! $last instanceof Backup || ! $last->exists()) {
            return true;
        }

        return $last->date()->lt(now()->subHours($this->stalenessThresholdHours()));
    }

    public function stalenessThresholdHours(): int
    {
        return (int) config('booking.backups.staleness_threshold_hours', 48);
    }

    /**
     * The health-check status Spatie's own `backup:monitor` command would
     * compute for the configured destination, read directly rather than by
     * invoking that command.
     *
     * @return Collection<int, BackupDestinationStatus>
     */
    public function healthStatuses(): Collection
    {
        /** @var SpatieBackupConfig $config */
        $config = app(SpatieBackupConfig::class);

        return BackupDestinationStatusFactory::createForMonitorConfig($config->monitoredBackups);
    }

    /**
     * A plain-text technical report combining the destination disk, the
     * latest database and media artefacts, staleness, and the current
     * health-check outcome — the downloadable report the administration
     * screen offers. Kept in plain English regardless of who downloads it,
     * the same convention this project already applies to logs and error
     * messages, since this is an operational diagnostic rather than
     * interface copy.
     */
    public function technicalReport(): string
    {
        $lines = [
            'Booking Portal — Backup Technical Report',
            'Generated: '.now()->toDateTimeString().' UTC',
            'Destination disk: '.$this->database->diskName(),
            'Staleness threshold: '.$this->stalenessThresholdHours().' hours',
            '',
            'Database backup',
        ];

        $lastDatabase = $this->lastDatabaseBackup();

        if ($lastDatabase instanceof Backup && $lastDatabase->exists()) {
            $lines[] = '  Latest: '.$lastDatabase->date()->toDateTimeString().' ('.$lastDatabase->date()->diffForHumans().')';
            $lines[] = '  Size: '.Number::fileSize($lastDatabase->sizeInBytes());
            $lines[] = '  Staleness: '.($this->isDatabaseBackupStale() ? 'STALE' : 'OK');
        } else {
            $lines[] = '  No database backup exists yet.';
        }

        $lines[] = '';
        $lines[] = 'Media backup';

        $lastMediaDate = $this->lastMediaBackupDate();

        if ($lastMediaDate instanceof Carbon) {
            $lines[] = '  Latest generation: '.$lastMediaDate->toDateTimeString().' ('.$lastMediaDate->diffForHumans().')';
        } else {
            $lines[] = '  No media backup generation exists yet.';
        }

        $lines[] = '';
        $lines[] = 'Health checks';

        foreach ($this->healthStatuses() as $status) {
            $diskName = $status->backupDestination()->diskName();
            $healthy = $status->isHealthy();
            $lines[] = '  ['.$diskName.'] '.($healthy ? 'healthy' : 'unhealthy');

            foreach ($status->getHealthCheckFailures() as $failure) {
                $lines[] = '    - ['.$failure->healthCheck()->name().'] '.$failure->exception()->getMessage();
            }
        }

        return implode(PHP_EOL, $lines).PHP_EOL;
    }

    /**
     * A media generation directory is named `media/{Y-m-d_His}` — parsed
     * back into its own timestamp rather than read from the directory's
     * filesystem metadata, since that metadata reflects when the directory
     * was last listed, not when the generation was written.
     */
    private function mediaGenerationDate(string $path): Carbon
    {
        $parsed = Carbon::createFromFormat('Y-m-d_His', basename($path));

        return $parsed instanceof Carbon ? $parsed : now();
    }
}
