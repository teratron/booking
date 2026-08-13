<?php

declare(strict_types=1);

namespace App\Services\Cabinet;

use App\Exceptions\PhotoLimitExceededException;
use App\Models\Object_;
use App\Services\Settings\SettingsRepository;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Owns every write an owner may make to their object's photo gallery —
 * upload, delete, caption, and primary-photo selection — plus the portal
 * settings that bound an upload (count, size, dimensions). Reordering is
 * deliberately absent here: it is a plain column reorder with no invariant
 * to protect, handled directly by the cabinet table's own drag-to-reorder
 * feature against the same tenant-scoped query this service's callers
 * already sit behind (see `PhotosTable`).
 *
 * Uploads and deletions go through `spatie/laravel-medialibrary`'s own
 * mechanism exactly as {@see Object_::registerMediaCollections()} declares
 * it — this service adds the portal's own count limit and the owner-facing
 * caption/primary bookkeeping, never a second image-processing path.
 *
 * Every write here applies immediately, regardless of the object's
 * publication state — deliberately outside {@see ObjectEditService}
 * and never touching `ModerationPipeline`, even though the portal's own
 * moderated-change-type vocabulary lists "photographs" as a candidate.
 * A gallery is a set of individually addressable rows, not a snapshot of
 * scalar columns: nothing here can be reduced to the flat `fill()`-and-save
 * shape a moderator's later approval replays, the same structural reason
 * contact-channel editing was exempted for. Should photo moderation become a
 * real requirement, it needs its own review representation (e.g. a proposed
 * gallery diff a moderator approves or rejects as a whole) rather than being
 * bent through a pipeline built for column snapshots.
 */
final class ObjectPhotoService
{
    private const COLLECTION = 'photos';

    public function __construct(private readonly SettingsRepository $settings) {}

    public function maxPhotos(): int
    {
        return (int) $this->settings->get('media.object_photos_max_count');
    }

    public function maxUploadKilobytes(): int
    {
        return (int) $this->settings->get('media.upload_max_kilobytes');
    }

    /** @return list<string> */
    public function allowedMimeTypes(): array
    {
        /** @var list<string> $types */
        $types = $this->settings->get('media.allowed_mime_types');

        return $types;
    }

    /** @return array{width: int, height: int} */
    public function maxDimensions(): array
    {
        return [
            'width' => (int) $this->settings->get('media.image_max_width'),
            'height' => (int) $this->settings->get('media.image_max_height'),
        ];
    }

    /**
     * The Laravel `dimensions` validation rule string for the portal's
     * currently configured maximum — read fresh on every call rather than
     * cached, since an administrator may change the setting between one
     * upload attempt and the next.
     */
    public function dimensionsRule(): string
    {
        $dimensions = $this->maxDimensions();

        return "dimensions:max_width={$dimensions['width']},max_height={$dimensions['height']}";
    }

    /**
     * How many more photos $object may accept before hitting the portal's
     * configured maximum. Never negative — an object that already exceeds a
     * since-lowered limit simply accepts no more uploads until some are
     * removed, rather than reporting a nonsensical negative allowance.
     */
    public function remainingSlots(Object_ $object): int
    {
        return max(0, $this->maxPhotos() - $object->getMedia(self::COLLECTION)->count());
    }

    /**
     * Attaches a file already stored on $disk (typically the destination of
     * a Filament upload field's own save step) to $object's photo gallery.
     *
     * @throws PhotoLimitExceededException when $object's gallery is already at the configured maximum
     */
    public function uploadFromDisk(Object_ $object, string $path, string $disk): Media
    {
        if ($object->getMedia(self::COLLECTION)->count() >= $this->maxPhotos()) {
            throw PhotoLimitExceededException::forObject((int) $object->id, $this->maxPhotos());
        }

        return $object->addMediaFromDisk($path, $disk)->toMediaCollection(self::COLLECTION);
    }

    public function setCaption(Media $photo, ?string $caption): void
    {
        $photo->setCustomProperty('caption', $caption ?? '');
        $photo->save();
    }

    /**
     * Marks $photo as $object's single primary photo, clearing the flag from
     * every other photo in the same gallery — the collection may hold at
     * most one primary photo at a time, so setting one is also, implicitly,
     * unsetting whichever photo held it before.
     */
    public function markPrimary(Object_ $object, Media $photo): void
    {
        $object->getMedia(self::COLLECTION)->each(function (Media $candidate) use ($photo): void {
            $shouldBePrimary = $candidate->is($photo);

            if ($candidate->getCustomProperty('is_primary', false) === $shouldBePrimary) {
                return;
            }

            $candidate->setCustomProperty('is_primary', $shouldBePrimary);
            $candidate->save();
        });
    }

    /**
     * Deletes $photo, including its stored files and generated conversions.
     *
     * Always resolves through the base `Media` class rather than whatever
     * subclass instance was passed in: Spatie's own file-cleanup observer is
     * registered against `Media::class` specifically
     * (`media-library.media_model`), and Eloquent's model-event dispatch is
     * keyed to the exact class an instance was fired from. Deleting through
     * `App\Models\ObjectPhoto` — the cabinet's own tenant-scoped query model
     * for these same rows — would silently skip that cleanup and leave
     * orphaned files on disk.
     */
    public function delete(Media $photo): void
    {
        Media::query()->whereKey($photo->getKey())->first()?->delete();
    }
}
