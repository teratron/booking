<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PlacementPackage;
use App\Models\User;

/**
 * Packages are portal-wide configuration — like the object type registry, a
 * category-scoped grant governs objects *of* a category, not the package
 * definitions administrators may sell against that category — so no scope
 * axis narrows this resource; only an unrestricted grant reaches it.
 */
final class PlacementPackagePolicy extends ScopedPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->authorize($user, 'commerce.view');
    }

    public function view(User $user, PlacementPackage $placementPackage): bool
    {
        return $this->authorize($user, 'commerce.view');
    }

    public function create(User $user): bool
    {
        return $this->authorize($user, 'commerce.create');
    }

    public function update(User $user, PlacementPackage $placementPackage): bool
    {
        return $this->authorize($user, 'commerce.edit');
    }

    public function delete(User $user, PlacementPackage $placementPackage): bool
    {
        return $this->authorize($user, 'commerce.delete');
    }
}
