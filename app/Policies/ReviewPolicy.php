<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Object_;
use App\Models\Review;
use App\Models\User;
use App\Services\Authorization\CabinetAccessResolver;
use App\Services\Authorization\ScopeAuthorizer;
use App\Services\Reviews\ReviewModerationService;

/**
 * Guards one review by delegating to its owning object's own ownership
 * rules — a review carries no scope of its own, so every decision here
 * resolves through {@see Review::object()} and reuses exactly the two axes
 * {@see Object_Policy} already checks: a staff account's country/territory/
 * category scope, or an owner/staff-member's direct relationship to the
 * object via {@see CabinetAccessResolver}.
 *
 * {@see self::update()} and {@see self::delete()} are refused unconditionally,
 * including for the object's own owner — there is deliberately no path
 * anywhere in this policy that grants either ability. A review's rating,
 * body, and author fields must stay exactly as submitted, and removal is an
 * administrative concern (soft delete with a recorded `hidden_reason`) that
 * has no owner-facing surface yet. The only writes an owner may make are the
 * two purpose-built abilities below, {@see self::reply()} and
 * {@see self::report()}, each scoped the same way as every other check here.
 */
final class ReviewPolicy extends ScopedPolicy
{
    public function __construct(
        ScopeAuthorizer $authorizer,
        private readonly CabinetAccessResolver $cabinetAccess,
    ) {
        parent::__construct($authorizer);
    }

    public function viewAny(User $user): bool
    {
        return $user->can('object.view');
    }

    public function view(User $user, Review $review): bool
    {
        return $this->authorizeAgainstObject($user, 'object.view', $review);
    }

    /**
     * A review is never created through this policy's owning resource — see
     * the owner cabinet's review screen for why submission is out of scope
     * there.
     */
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Review $review): bool
    {
        return false;
    }

    public function delete(User $user, Review $review): bool
    {
        return false;
    }

    /**
     * Whether $user may post the object's single owner reply to $review —
     * the review's own {@see Review::owner_reply} nullability is what
     * actually limits this to once; this method only answers "may this actor
     * touch this row at all", the same separation every other ability here
     * keeps.
     */
    public function reply(User $user, Review $review): bool
    {
        return $this->authorizeAgainstObject($user, 'object.edit', $review);
    }

    /**
     * Whether $user may flag $review for administrator attention.
     */
    public function report(User $user, Review $review): bool
    {
        return $this->authorizeAgainstObject($user, 'object.edit', $review);
    }

    /**
     * Whether $user may publish, reject, or hide $review — the three
     * moderation decisions {@see ReviewModerationService}
     * makes. Gated on `moderation.edit`, the same permission
     * {@see ModerationRequestPolicy::update()} already uses for the
     * equivalent decision on the generic moderation queue — a review's own
     * dedicated resource is a different mechanism, not a different grant.
     */
    public function publish(User $user, Review $review): bool
    {
        return $this->authorizeModerationDecision($user, $review);
    }

    public function reject(User $user, Review $review): bool
    {
        return $this->authorizeModerationDecision($user, $review);
    }

    public function hide(User $user, Review $review): bool
    {
        return $this->authorizeModerationDecision($user, $review);
    }

    private function authorizeModerationDecision(User $user, Review $review): bool
    {
        $object = $review->object;

        if (! $object instanceof Object_) {
            return false;
        }

        return $this->authorize(
            $user,
            'moderation.edit',
            (int) $object->country_id,
            (int) $object->territory_id,
            (int) $object->object_type_id,
        );
    }

    private function authorizeAgainstObject(User $user, string $permission, Review $review): bool
    {
        $object = $review->object;

        if (! $object instanceof Object_) {
            return false;
        }

        if ($this->authorize(
            $user,
            $permission,
            (int) $object->country_id,
            (int) $object->territory_id,
            (int) $object->object_type_id,
        )) {
            return true;
        }

        return $this->cabinetAccess->authorize($user, $permission, $object);
    }
}
