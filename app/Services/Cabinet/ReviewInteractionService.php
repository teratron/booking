<?php

declare(strict_types=1);

namespace App\Services\Cabinet;

use App\Exceptions\ReviewAlreadyRepliedException;
use App\Exceptions\ReviewAlreadyReportedException;
use App\Models\Review;
use App\Models\User;
use App\Policies\ReviewPolicy;

/**
 * The whole set of writes an owner may make to a review through the cabinet:
 * posting the object's single reply, and reporting a review for
 * administrator attention. Deliberately narrow method signatures — each
 * takes only the exact scalar value it may ever write, never an open data
 * array — so there is no parameter through which a caller could smuggle a
 * protected column (`rating`, `body`, `author_name`, `status`) into either
 * write, even by mistake. That is on top of, not instead of,
 * {@see ReviewPolicy} refusing the generic update/delete
 * abilities outright for every actor.
 */
final class ReviewInteractionService
{
    /**
     * @throws ReviewAlreadyRepliedException when $review already carries an owner reply
     */
    public function reply(Review $review, User $actor, string $body): void
    {
        if ($review->owner_reply !== null) {
            throw ReviewAlreadyRepliedException::forReview((int) $review->id);
        }

        $review->forceFill([
            'owner_reply' => $body,
            'owner_replied_at' => now(),
        ])->save();
    }

    /**
     * @throws ReviewAlreadyReportedException when $review has already been reported
     */
    public function report(Review $review, User $actor, string $reason): void
    {
        if ($review->reported_at !== null) {
            throw ReviewAlreadyReportedException::forReview((int) $review->id);
        }

        $review->forceFill([
            'reported_at' => now(),
            'reported_by' => $actor->id,
            'report_reason' => $reason,
        ])->save();
    }
}
