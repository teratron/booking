<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ErrorPage;
use App\Models\User;

/**
 * Error page copy is portal-wide addressing configuration, not scoped to a
 * country, territory, or category — the same reasoning {@see RedirectPolicy}
 * applies to the redirect table.
 */
final class ErrorPagePolicy extends ScopedPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->authorize($user, 'seo.view');
    }

    public function view(User $user, ErrorPage $errorPage): bool
    {
        return $this->authorize($user, 'seo.view');
    }

    public function create(User $user): bool
    {
        return $this->authorize($user, 'seo.edit');
    }

    public function update(User $user, ErrorPage $errorPage): bool
    {
        return $this->authorize($user, 'seo.edit');
    }

    public function delete(User $user, ErrorPage $errorPage): bool
    {
        return $this->authorize($user, 'seo.edit');
    }
}
