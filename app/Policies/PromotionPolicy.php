<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Object_;
use App\Models\Promotion;
use App\Models\User;
use App\Services\Authorization\CabinetAccessResolver;
use App\Services\Authorization\ScopeAuthorizer;

/**
 * A promotion always carries a territory (`territory_id`, required, unlike
 * `NewsItem`'s optional one) — no country or category column exists on the
 * table to narrow against.
 *
 * A promotion is always authored through the owner cabinet, against a
 * concrete object it belongs to — it has no `role_scopes` grant of its own
 * to resolve against, so every check here also falls back to
 * {@see CabinetAccessResolver}, mirroring {@see ObjectPhotoPolicy}'s
 * identical two-axis shape for the same reason.
 */
final class PromotionPolicy extends ScopedPolicy
{
    public function __construct(
        ScopeAuthorizer $authorizer,
        private readonly CabinetAccessResolver $cabinetAccess,
    ) {
        parent::__construct($authorizer);
    }

    public function viewAny(User $user): bool
    {
        return $user->can('content.view');
    }

    public function view(User $user, Promotion $promotion): bool
    {
        return $this->authorizeAgainst($user, 'content.view', $promotion);
    }

    public function create(User $user): bool
    {
        return $user->can('content.create');
    }

    public function update(User $user, Promotion $promotion): bool
    {
        return $this->authorizeAgainst($user, 'content.edit', $promotion);
    }

    public function publish(User $user, Promotion $promotion): bool
    {
        return $this->authorizeAgainst($user, 'content.publish', $promotion);
    }

    public function delete(User $user, Promotion $promotion): bool
    {
        return $this->authorizeAgainst($user, 'content.delete', $promotion);
    }

    public function restore(User $user, Promotion $promotion): bool
    {
        return $this->authorizeAgainst($user, 'content.delete', $promotion);
    }

    private function authorizeAgainst(User $user, string $permission, Promotion $promotion): bool
    {
        if ($this->authorize($user, $permission, null, (int) $promotion->territory_id, null)) {
            return true;
        }

        $object = $promotion->object;

        return $object instanceof Object_ && $this->cabinetAccess->authorize($user, $permission, $object);
    }
}
