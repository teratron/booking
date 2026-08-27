<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PlacementHistory;
use App\Models\User;

/**
 * A read-only history row, reached only through its owning object's own
 * edit page — which already narrowed access by that object's country,
 * territory, and category before this relation manager ever renders. No
 * separate scope axis is derived here; the gate is the same `object.view`
 * grant the parent object's own policy already required. Granting a new
 * placement is a distinct, separately-scoped action
 * ({@see Object_Policy::grantPlacement()}); reading what was already
 * granted is not more sensitive than reading the object itself.
 */
final class PlacementHistoryPolicy extends ScopedPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->authorize($user, 'object.view');
    }

    public function view(User $user, PlacementHistory $history): bool
    {
        return $this->authorize($user, 'object.view');
    }
}
