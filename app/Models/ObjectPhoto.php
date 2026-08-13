<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Scopes\ModerationScope;
use App\Models\Scopes\ObjectPhotoCollectionScope;
use App\Policies\ObjectPhotoPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * The owner cabinet's own scoped view of one object's uploaded photographs —
 * the same shared `media` table every `HasMedia` model in this codebase
 * writes to, narrowed ({@see ObjectPhotoCollectionScope}) to the rows that
 * belong to an `Object_` and sit in its `photos` collection. Exists purely so
 * the cabinet's photo-management resource can declare the `object()`
 * relationship Filament's own tenancy scoping looks for on every
 * tenant-scoped resource model (the same convention `CabinetPanelProvider`
 * documents for rooms, prices, and services) — Spatie's polymorphic `Media`
 * model has no fixed relation back to any one owning model to lean on.
 *
 * Uploads are still written through `Object_::addMedia()`/`addMediaFromDisk()`
 * as ordinary `Media` rows (the model that actually gets persisted, per
 * `getMediaModel()`'s own default); this subclass only changes how those same
 * rows are queried, scoped, and authorized from the cabinet side. One
 * consequence of that: Spatie's own file-cleanup observer is registered
 * against `Media::class` specifically, and Eloquent dispatches model events
 * keyed to the exact class an instance was fired from — deleting through this
 * subclass would silently skip that cleanup. {@see
 * \App\Services\Cabinet\ObjectPhotoService::delete()} works around this by
 * deleting through the base `Media` class explicitly.
 */
#[UsePolicy(ObjectPhotoPolicy::class)]
final class ObjectPhoto extends Media
{
    #[Override]
    protected static function booted(): void
    {
        self::addGlobalScope(new ObjectPhotoCollectionScope);
    }

    /**
     * Explicitly strips `Object_`'s own `ModerationScope`, the same escape
     * hatch `CabinetPanelProvider::resolveTenantUsing()` reaches for and for
     * the identical reason: a draft or otherwise not-yet-approved object is
     * exactly the state an owner spends the most time uploading photographs
     * for, before it has ever reached publication. Left on, this relation
     * would silently resolve to null for every such object — including on
     * {@see ObjectPhotoPolicy}'s own authorization checks —
     * making every photo action fail closed for the owner it is meant to
     * serve, not merely for a genuine outsider.
     *
     * @return BelongsTo<Object_, $this>
     */
    public function object(): BelongsTo
    {
        return $this->belongsTo(Object_::class, 'model_id')->withoutGlobalScope(ModerationScope::class);
    }
}
