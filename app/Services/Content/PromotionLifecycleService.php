<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Exceptions\ContentScheduleRefusedException;
use App\Jobs\PromotionArchivalJob;
use App\Models\Promotion;
use App\Models\User;
use App\Services\Audit\AuditJournal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * The promotion form's lifecycle actions — everything beyond an ordinary
 * field save.
 *
 * Carries the same "administrator publish must set `moderation_status`
 * explicitly" obligation `NewsItem`'s own lifecycle service documents. The
 * automatic end-date archival {@see PromotionArchivalJob} performs
 * is a separate, scheduled path — this service's own {@see archive()} is
 * the administrator-triggered equivalent, available on demand rather than
 * only once `ends_at` arrives.
 */
final class PromotionLifecycleService
{
    public function __construct(private readonly AuditJournal $journal) {}

    /**
     * @throws ContentScheduleRefusedException when $startsAt is not in the future
     */
    public function schedule(Promotion $promotion, Carbon $startsAt, User $actor): void
    {
        if (! $startsAt->isFuture()) {
            throw ContentScheduleRefusedException::notInFuture('Promotion', (int) $promotion->id);
        }

        $previousStatus = $promotion->getOriginal('status');
        $promotion->status = 'scheduled';
        $promotion->starts_at = $startsAt;
        $promotion->save();

        $this->journal->record(
            'promotion_scheduled',
            $promotion,
            ['status' => $previousStatus],
            ['status' => 'scheduled', 'starts_at' => $startsAt->toDateString()],
            $actor,
            ['content'],
        );

        $this->invalidateContentCaches($promotion);
    }

    public function publish(Promotion $promotion, User $actor): void
    {
        $previousStatus = $promotion->getOriginal('status');
        $previousModerationStatus = $promotion->getOriginal('moderation_status');

        $promotion->status = 'published';
        $promotion->moderation_status = 'approved';

        // `starts_at` is a NOT NULL column (unlike Article/NewsItem's
        // nullable `publish_at`) — every promotion always has one from
        // creation, so only "still in the future" needs correcting here.
        if ($promotion->starts_at->isFuture()) {
            $promotion->starts_at = now();
        }

        $promotion->save();

        $this->journal->record(
            'promotion_published',
            $promotion,
            ['status' => $previousStatus, 'moderation_status' => $previousModerationStatus],
            ['status' => 'published', 'moderation_status' => 'approved'],
            $actor,
            ['content'],
        );

        $this->invalidateContentCaches($promotion);
    }

    /**
     * Administrator-triggered early archival — the terminal state
     * `PromotionArchivalJob` also applies automatically once `ends_at`
     * passes, available here on demand.
     */
    public function archive(Promotion $promotion, User $actor): void
    {
        $previousStatus = $promotion->getOriginal('status');
        $promotion->status = 'archived';
        $promotion->save();

        $this->journal->record('promotion_archived', $promotion, ['status' => $previousStatus], ['status' => 'archived'], $actor, ['content']);

        $this->invalidateContentCaches($promotion);
    }

    public function delete(Promotion $promotion, User $actor): void
    {
        $previousStatus = $promotion->getOriginal('status');
        $promotion->delete();

        $this->journal->record('promotion_deleted', $promotion, ['status' => $previousStatus], [], $actor, ['content']);

        $this->invalidateContentCaches($promotion);
    }

    public function restore(Promotion $promotion, User $actor): void
    {
        $promotion->restore();

        $this->journal->record('promotion_restored', $promotion, [], [], $actor, ['content']);

        $this->invalidateContentCaches($promotion);
    }

    /**
     * Publishing, scheduling, archiving, deleting, and restoring all change
     * what a public-facing read of this promotion — and of its object page
     * and territory — would return.
     */
    private function invalidateContentCaches(Promotion $promotion): void
    {
        Cache::tags(['content', "promotion:{$promotion->id}", "object:{$promotion->object_id}", "territory:{$promotion->territory_id}"])->flush();
    }
}
