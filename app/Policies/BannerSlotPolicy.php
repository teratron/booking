<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\BannerSlot;
use App\Models\User;

/**
 * Slots are portal-wide inventory positions, not owned by any one country,
 * territory, or object category — like the object type registry, no scope
 * axis narrows this resource; only an unrestricted grant reaches it.
 */
final class BannerSlotPolicy extends ScopedPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->authorize($user, 'advertising.view');
    }

    public function view(User $user, BannerSlot $bannerSlot): bool
    {
        return $this->authorize($user, 'advertising.view');
    }

    public function create(User $user): bool
    {
        return $this->authorize($user, 'advertising.create');
    }

    public function update(User $user, BannerSlot $bannerSlot): bool
    {
        return $this->authorize($user, 'advertising.edit');
    }

    public function delete(User $user, BannerSlot $bannerSlot): bool
    {
        return $this->authorize($user, 'advertising.delete');
    }
}
