<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\RoleScope;
use App\Models\User;

/**
 * A grant row, reached only through the staff-administration screen's own
 * relation manager — a screen {@see StaffPolicy} already restricts to the
 * chief administrator alone. No separate scope axis is derived here; the
 * gate is the same `user.edit` grant the staff resource's own
 * `$permissionPrefix` already requires to reach this page at all.
 */
final class RoleScopePolicy extends ScopedPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->authorize($user, 'user.edit');
    }

    public function view(User $user, RoleScope $scope): bool
    {
        return $this->authorize($user, 'user.edit');
    }
}
