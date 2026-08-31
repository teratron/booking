<?php

declare(strict_types=1);

namespace App\Services\Reviews;

use App\Http\Controllers\Public\ObjectPageController;
use App\Models\Review;
use App\Models\User;
use App\Services\Audit\AuditJournal;
use App\Services\Moderation\ModerationDecisionService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The three decisions a moderator makes on a submitted review through the
 * admin `ReviewResource` — publish, reject, or (for a review already live)
 * hide. Distinct from {@see ModerationDecisionService},
 * which settles the generic polymorphic moderation queue: a review is a
 * pure create with no "previous published state" to diff against and no
 * registered submitter to notify, so it never enters that queue at all —
 * see {@see ReviewSubmissionService}, the sole writer of a new review row.
 *
 * No notification is dispatched on any decision here — a review is always
 * authored by a named guest with no stored contact address, so there is no
 * channel to notify through, unlike the generic queue's registered
 * `submitted_by` account.
 */
final class ReviewModerationService
{
    public function __construct(
        private readonly AuditJournal $journal,
    ) {}

    public function publish(Review $review, User $actor): void
    {
        DB::transaction(function () use ($review, $actor): void {
            $review->forceFill(['status' => 'published'])->save();

            $this->journal->record('review_published', $review, ['status' => 'pending'], ['status' => 'published'], $actor, ['reviews']);
        });

        $this->flushObjectProfileCache($review);
    }

    public function reject(Review $review, string $reason, User $actor): void
    {
        DB::transaction(function () use ($review, $reason, $actor): void {
            $review->forceFill(['status' => 'rejected', 'rejection_reason' => $reason])->save();

            $this->journal->record('review_rejected', $review, ['status' => 'pending'], ['status' => 'rejected', 'reason' => $reason], $actor, ['reviews']);
        });
    }

    /**
     * Removes a published review from public view — the upheld-report path,
     * always soft delete with a recorded reason, never the `rejected`
     * status a review that was never live uses.
     */
    public function hide(Review $review, string $reason, User $actor): void
    {
        DB::transaction(function () use ($review, $reason, $actor): void {
            $review->forceFill(['hidden_reason' => $reason, 'deleted_by' => $actor->id])->save();
            $review->delete();

            $this->journal->record('review_hidden', $review, ['status' => 'published'], ['status' => 'hidden', 'reason' => $reason], $actor, ['reviews']);
        });

        $this->flushObjectProfileCache($review);
    }

    /**
     * Publish and hide both change what the owning object's cached profile
     * would render — {@see ObjectPageController}
     * caches it under this exact tag set, the same one every other write
     * that changes object-page content already flushes (availability,
     * bumps, placement, content publication). reject() needs no flush: a
     * rejected review was never shown, so nothing cached changes.
     */
    private function flushObjectProfileCache(Review $review): void
    {
        Cache::tags(['catalog', "territory:{$review->territory_id}", "object:{$review->object_id}"])->flush();
    }
}
