<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Banner;
use App\Models\User;

/**
 * Banners are sold and managed portal-wide — a campaign is not owned by any
 * one country, territory, or object category — so no scope axis narrows this
 * resource; only an unrestricted grant reaches it.
 */
final class BannerPolicy extends ScopedPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->authorize($user, 'advertising.view');
    }

    public function view(User $user, Banner $banner): bool
    {
        return $this->authorize($user, 'advertising.view');
    }

    public function create(User $user): bool
    {
        return $this->authorize($user, 'advertising.create');
    }

    public function update(User $user, Banner $banner): bool
    {
        return $this->authorize($user, 'advertising.edit');
    }

    public function delete(User $user, Banner $banner): bool
    {
        return $this->authorize($user, 'advertising.delete');
    }
}
