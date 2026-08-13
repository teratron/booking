<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PromotionLabel;
use App\Models\User;

/**
 * Promotional labels are portal-wide configuration, like the placement
 * package and tier registries they sit beside — a label is not owned by any
 * one country, territory, or object category, so no scope axis narrows this
 * resource; only an unrestricted grant reaches it. Which object currently
 * carries a label is a separate, per-object concern this policy does not
 * govern.
 */
final class PromotionLabelPolicy extends ScopedPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->authorize($user, 'advertising.view');
    }

    public function view(User $user, PromotionLabel $promotionLabel): bool
    {
        return $this->authorize($user, 'advertising.view');
    }

    public function create(User $user): bool
    {
        return $this->authorize($user, 'advertising.create');
    }

    public function update(User $user, PromotionLabel $promotionLabel): bool
    {
        return $this->authorize($user, 'advertising.edit');
    }

    public function delete(User $user, PromotionLabel $promotionLabel): bool
    {
        return $this->authorize($user, 'advertising.delete');
    }
}
