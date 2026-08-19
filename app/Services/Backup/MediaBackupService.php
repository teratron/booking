<?php

declare(strict_types=1);

namespace App\Services\Backup;

use App\Exceptions\BackupIntegrityFailedException;
use Illuminate\Support\Facades\Storage;

/**
 * Mirrors the media disk into its own timestamped generation on the backup
 * destination disk, on a schedule independent of the database dump — media
 * is bulkier and changes on a different rhythm than the catalog's own
 * writes, and the two artefacts carry different restore urgency.
 *
 * This does not reuse Spatie's own file-backup pipeline: that pipeline zips
 * *local* filesystem paths via `Symfony\Component\Finder\Finder`, and media
 * lives on a remote, S3-compatible disk. A plain file-to-file mirror, kept
 * to its own generation directory per run, is the mechanism that actually
 * matches where the source data lives.
 */
final class MediaBackupService
{
    private const GENERATION_PREFIX = 'media';

    public function __construct(private readonly BackupDestinationDisk $destinationDisk) {}

    /**
     * The disk the media library itself currently writes to — read from
     * Spatie Media Library's own config rather than assumed, so this
     * service backs up wherever media actually lives even if that
     * configuration changes.
     */
    public function sourceDiskName(): string
    {
        return (string) config('media-library.disk_name', 'public');
    }

    public function destinationDiskName(): string
    {
        return $this->destinationDisk->name();
    }

    /**
     * Copies every file on the media disk into a fresh generation directory
     * and confirms the copied file count matches the source before
     * returning the generation's own path.
     *
     * @throws BackupIntegrityFailedException when the destination's file
     *                                        count does not match the source disk's own count — the
     *                                        file-mirror equivalent of a corrupted zip for a backup shaped
     *                                        as individually streamed objects rather than one archive.
     */
    public function run(): string
    {
        $generation = self::GENERATION_PREFIX.'/'.now()->format('Y-m-d_His');

        $source = Storage::disk($this->sourceDiskName());
        $destination = Storage::disk($this->destinationDiskName());

        $files = $source->allFiles();

        foreach ($files as $file) {
            $stream = $source->readStream($file);

            if ($stream === null) {
                continue;
            }

            $destination->put("{$generation}/{$file}", $stream);

            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if (count($destination->allFiles($generation)) !== count($files)) {
            throw BackupIntegrityFailedException::media($this->destinationDiskName(), $generation);
        }

        return $generation;
    }

    /**
     * Deletes every media generation beyond the most recent $keep, oldest
     * first. Generation directories sort correctly by name alone — each is
     * named from `now()->format('Y-m-d_His')`, so a descending string sort
     * is already a descending chronological sort.
     */
    public function pruneGenerations(int $keep): int
    {
        $destination = Storage::disk($this->destinationDiskName());

        $generations = collect($destination->directories(self::GENERATION_PREFIX))
            ->sortByDesc(fn (string $path) => $path)
            ->values();

        $stale = $generations->slice(max($keep, 0));

        foreach ($stale as $path) {
            $destination->deleteDirectory($path);
        }

        return $stale->count();
    }
}
