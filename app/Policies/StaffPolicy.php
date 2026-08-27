<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Gates the staff-administration resource itself — deliberately not
 * {@see UserPolicy}. `StaffResource` and `OwnerResource` both expose the
 * `User` model, and Laravel resolves exactly one policy per model class;
 * reusing `UserPolicy`'s `user.*`-permission checks here would let a
 * country or region administrator (holding `user.edit`, needed for owner
 * management) reach a screen that lets them alter their own or a peer
 * administrator's role grants. This policy is invoked directly by
 * `StaffResource`'s own authorization override rather than through
 * Laravel's model-policy resolution, so the two never collide.
 *
 * Creating or editing another staff account is the most sensitive action in
 * the panel — the open question of whether scoped delegation should ever
 * be permitted here is left to a future specification amendment. Every
 * action is chief-administrator-only until it is resolved.
 */
final class StaffPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isChiefAdministrator($user);
    }

    public function view(User $user, User $target): bool
    {
        return $this->isChiefAdministrator($user);
    }

    public function create(User $user): bool
    {
        return $this->isChiefAdministrator($user);
    }

    public function update(User $user, User $target): bool
    {
        return $this->isChiefAdministrator($user);
    }

    public function delete(User $user, User $target): bool
    {
        return $this->isChiefAdministrator($user);
    }

    private function isChiefAdministrator(User $user): bool
    {
        return $user->hasRole('chief_administrator');
    }
}
