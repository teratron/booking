<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Redirect;
use App\Models\User;

/**
 * Redirects are portal-wide addressing configuration, not scoped to a
 * country, territory, or category — the same reasoning
 * {@see ObjectTypePolicy} applies to the type registry.
 */
final class RedirectPolicy extends ScopedPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->authorize($user, 'settings.view');
    }

    public function view(User $user, Redirect $redirect): bool
    {
        return $this->authorize($user, 'settings.view');
    }

    public function create(User $user): bool
    {
        return $this->authorize($user, 'settings.edit');
    }

    public function update(User $user, Redirect $redirect): bool
    {
        return $this->authorize($user, 'settings.edit');
    }

    public function delete(User $user, Redirect $redirect): bool
    {
        return $this->authorize($user, 'settings.edit');
    }
}
