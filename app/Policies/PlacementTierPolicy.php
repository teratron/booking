<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PlacementTier;
use App\Models\User;

/**
 * The four ranks are structural, not administrator-created data — this
 * policy authorizes editing a tier's presentation (label, badges, colours,
 * active flag), never creating or deleting one. No `create`/`delete` method
 * is declared: the resource registers no create page and no delete action,
 * so strict authorization mode never needs to resolve either ability for
 * this model. Gated on the same `commerce` permission the package registry
 * uses, since both are the same administrator-facing configuration surface.
 */
final class PlacementTierPolicy extends ScopedPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->authorize($user, 'commerce.view');
    }

    public function view(User $user, PlacementTier $placementTier): bool
    {
        return $this->authorize($user, 'commerce.view');
    }

    public function update(User $user, PlacementTier $placementTier): bool
    {
        return $this->authorize($user, 'commerce.edit');
    }
}
