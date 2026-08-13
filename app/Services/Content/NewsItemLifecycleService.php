<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Exceptions\ContentScheduleRefusedException;
use App\Models\NewsItem;
use App\Models\User;
use App\Services\Audit\AuditJournal;
use Illuminate\Support\Carbon;

/**
 * The news item form's lifecycle actions — everything beyond an ordinary
 * field save.
 *
 * Unlike `Article`, `NewsItem` carries a real `moderation_status` column: an
 * administrator authoring or publishing one is still the trusted act (no
 * queue), but that trust must be recorded on the column the model's own
 * `FiltersModeration` scope reads, or a published item stays invisible to
 * every default query — see `ObjectLifecycleService::publish()`'s docblock
 * for the same gap, once real, now fixed there and never repeated here.
 * Owner-authored submissions do not run through this service at all; they
 * arrive through a later phase's cabinet and the moderation pipeline that
 * phase wires in.
 */
final class NewsItemLifecycleService
{
    public function __construct(
        private readonly AuditJournal $journal,
        private readonly ContentPublicationService $publication,
    ) {}

    public function saveAsDraft(NewsItem $newsItem, User $actor): void
    {
        $previousStatus = $newsItem->getOriginal('status');
        $newsItem->status = 'draft';
        $newsItem->publish_at = null;
        $newsItem->save();

        $this->journal->record('news_item_saved_as_draft', $newsItem, ['status' => $previousStatus], ['status' => 'draft'], $actor, ['content']);

        $this->invalidateContentCaches($newsItem);
    }

    /**
     * @throws ContentScheduleRefusedException when $publishAt is not in the future
     */
    public function schedule(NewsItem $newsItem, Carbon $publishAt, User $actor): void
    {
        if (! $publishAt->isFuture()) {
            throw ContentScheduleRefusedException::notInFuture('NewsItem', (int) $newsItem->id);
        }

        $previousStatus = $newsItem->getOriginal('status');
        $newsItem->status = 'scheduled';
        $newsItem->publish_at = $publishAt;
        $newsItem->save();

        $this->journal->record(
            'news_item_scheduled',
            $newsItem,
            ['status' => $previousStatus],
            ['status' => 'scheduled', 'publish_at' => $publishAt->toIso8601String()],
            $actor,
            ['content'],
        );

        $this->invalidateContentCaches($newsItem);
    }

    /**
     * Publishes $newsItem immediately as an administrator — sets both
     * `status` and `moderation_status` together, since an administrator
     * publishing is already the trusted act and this model's default query
     * enforces the latter globally.
     */
    public function publish(NewsItem $newsItem, User $actor): void
    {
        $previousStatus = $newsItem->getOriginal('status');
        $previousModerationStatus = $newsItem->getOriginal('moderation_status');

        $newsItem->status = 'published';
        $newsItem->moderation_status = 'approved';

        if ($newsItem->publish_at === null || $newsItem->publish_at->isFuture()) {
            $newsItem->publish_at = now();
        }

        $newsItem->save();

        $this->journal->record(
            'news_item_published',
            $newsItem,
            ['status' => $previousStatus, 'moderation_status' => $previousModerationStatus],
            ['status' => 'published', 'moderation_status' => 'approved'],
            $actor,
            ['content'],
        );

        $this->invalidateContentCaches($newsItem);
    }

    public function pin(NewsItem $newsItem, User $actor): void
    {
        $newsItem->is_pinned = true;
        $newsItem->save();

        $this->journal->record('news_item_pinned', $newsItem, ['is_pinned' => false], ['is_pinned' => true], $actor, ['content']);

        $this->invalidateContentCaches($newsItem);
    }

    public function unpin(NewsItem $newsItem, User $actor): void
    {
        $newsItem->is_pinned = false;
        $newsItem->save();

        $this->journal->record('news_item_unpinned', $newsItem, ['is_pinned' => true], ['is_pinned' => false], $actor, ['content']);

        $this->invalidateContentCaches($newsItem);
    }

    /**
     * Administrator-triggered early withdrawal — the same terminal state
     * {@see ContentPublicationService}'s scheduled sweep applies
     * automatically once `end_at` passes, available here on demand rather
     * than only once a date arrives. Distinct from {@see archive()}: a
     * withdrawn item's own page stays reachable, only its feed presence
     * ends.
     */
    public function withdraw(NewsItem $newsItem, User $actor): void
    {
        $previousStatus = $newsItem->getOriginal('status');
        $newsItem->status = 'withdrawn';
        $newsItem->save();

        $this->journal->record('news_item_withdrawn', $newsItem, ['status' => $previousStatus], ['status' => 'withdrawn'], $actor, ['content']);

        $this->invalidateContentCaches($newsItem);
    }

    public function archive(NewsItem $newsItem, User $actor): void
    {
        $previousStatus = $newsItem->getOriginal('status');
        $newsItem->delete();

        $this->journal->record('news_item_archived', $newsItem, ['status' => $previousStatus], [], $actor, ['content']);

        $this->invalidateContentCaches($newsItem);
    }

    public function restore(NewsItem $newsItem, User $actor): void
    {
        $newsItem->restore();

        $this->journal->record('news_item_restored', $newsItem, [], [], $actor, ['content']);

        $this->invalidateContentCaches($newsItem);
    }

    /**
     * Publishing, scheduling, pinning, withdrawing, archiving, and restoring
     * all change what a public-facing read of this news item — and of its
     * object page and territory, when scoped to either — would return.
     * Delegates to {@see ContentPublicationService}, the one place all
     * three content types' invalidation keys are enumerated.
     */
    private function invalidateContentCaches(NewsItem $newsItem): void
    {
        $this->publication->invalidate(
            'news',
            (int) $newsItem->id,
            $newsItem->object_id,
            $newsItem->territory_id === null ? [] : [$newsItem->territory_id],
        );
    }
}
