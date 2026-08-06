<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Module;
use App\Models\User;

/**
 * The registry is portal-wide configuration, so no scope axis narrows it —
 * every method resolves through the unrestricted branch of the shared
 * authorizer. Membership is fixed by the code that implements each module, so
 * create and delete are denied outright rather than left to a permission.
 */
final class ModulePolicy extends ScopedPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->authorize($user, 'settings.view');
    }

    public function view(User $user, Module $module): bool
    {
        return $this->authorize($user, 'settings.view');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Module $module): bool
    {
        return $this->authorize($user, 'settings.edit');
    }

    public function delete(User $user, Module $module): bool
    {
        return false;
    }
}
