<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AvailabilityHistory;
use App\Models\User;

/**
 * A read-only history row, reached only through its owning object's own
 * edit page — which already narrowed access by that object's country,
 * territory, and category before this relation manager ever renders. No
 * separate scope axis is derived here; the gate is the same `object.view`
 * grant the parent object's own policy already required.
 */
final class AvailabilityHistoryPolicy extends ScopedPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->authorize($user, 'object.view');
    }

    public function view(User $user, AvailabilityHistory $history): bool
    {
        return $this->authorize($user, 'object.view');
    }
}
