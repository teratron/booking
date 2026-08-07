<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Guards a single account against the actor's country grant. Owner accounts
 * carry only the country axis — unlike an object, an owner has no territory
 * or category of their own — so every check here resolves against that one
 * axis, leaving territory and category unset.
 *
 * The list narrowing the owner-management screen applies before this ever
 * runs removes unreachable rows from a page; this decides the record, which
 * is the half that still matters when a URL is typed rather than clicked.
 */
final class UserPolicy extends ScopedPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('user.view');
    }

    public function view(User $user, User $target): bool
    {
        return $this->authorizeAgainst($user, 'user.view', $target);
    }

    public function create(User $user): bool
    {
        return $user->can('user.create');
    }

    public function update(User $user, User $target): bool
    {
        return $this->authorizeAgainst($user, 'user.edit', $target);
    }

    /**
     * Suspending account access is not an ordinary edit — it is destructive
     * enough to refuse the account admission to every panel — so it is
     * gated the same way an object's archival is: against the delete verb,
     * not the edit one.
     */
    public function block(User $user, User $target): bool
    {
        return $this->authorizeAgainst($user, 'user.delete', $target);
    }

    /**
     * Lifting a suspension is gated the same way granting it is: both are
     * the delete-verb ability, mirroring how an object's own restore reuses
     * its delete permission rather than a separate one.
     */
    public function restore(User $user, User $target): bool
    {
        return $this->authorizeAgainst($user, 'user.delete', $target);
    }

    private function authorizeAgainst(User $user, string $permission, User $target): bool
    {
        return $this->authorize($user, $permission, $target->country_id);
    }
}
