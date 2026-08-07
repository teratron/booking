<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ObjectType;
use App\Models\User;

/**
 * The type registry defines what fields every object of a type exposes
 * portal-wide — a category-scoped grant governs objects *of* a category, not
 * the category's own definition, so no scope axis narrows this resource;
 * only an unrestricted grant reaches it. Gated on the same settings
 * permission the module registry uses, since both are portal-wide
 * configuration rather than an operational per-object action.
 */
final class ObjectTypePolicy extends ScopedPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->authorize($user, 'settings.view');
    }

    public function view(User $user, ObjectType $objectType): bool
    {
        return $this->authorize($user, 'settings.view');
    }

    public function create(User $user): bool
    {
        return $this->authorize($user, 'settings.edit');
    }

    public function update(User $user, ObjectType $objectType): bool
    {
        return $this->authorize($user, 'settings.edit');
    }

    public function delete(User $user, ObjectType $objectType): bool
    {
        return $this->authorize($user, 'settings.edit');
    }
}
