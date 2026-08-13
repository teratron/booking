<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Promotion;
use App\Models\User;

/**
 * A promotion always carries a territory (`territory_id`, required, unlike
 * `NewsItem`'s optional one) — no country or category column exists on the
 * table to narrow against.
 */
final class PromotionPolicy extends ScopedPolicy
{
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
        return $this->authorize($user, $permission, null, (int) $promotion->territory_id, null);
    }
}
