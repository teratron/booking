<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Territory;
use App\Models\User;

/**
 * Scoped on country and on the territory's own identity — a region-scoped
 * grant's subtree expansion (`ScopeAuthorizer`) matches a descendant
 * territory by its own id, which is what lets a region administrator manage
 * every city and resort beneath their region without a separate grant per
 * node.
 */
final class TerritoryPolicy extends ScopedPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('geography.view');
    }

    public function view(User $user, Territory $territory): bool
    {
        return $this->authorizeAgainst($user, 'geography.view', $territory);
    }

    public function create(User $user): bool
    {
        return $user->can('geography.create');
    }

    public function update(User $user, Territory $territory): bool
    {
        return $this->authorizeAgainst($user, 'geography.edit', $territory);
    }

    public function delete(User $user, Territory $territory): bool
    {
        return $this->authorizeAgainst($user, 'geography.delete', $territory);
    }

    private function authorizeAgainst(User $user, string $permission, Territory $territory): bool
    {
        return $this->authorize($user, $permission, (int) $territory->country_id, (int) $territory->id);
    }
}
