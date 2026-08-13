<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ArticleCategory;
use App\Models\User;

/**
 * The editorial taxonomy is portal-wide configuration shared across content
 * types — no scope axis narrows it, so only an unrestricted `content` grant
 * reaches this registry, the same posture `ObjectTypePolicy` takes for the
 * object-type registry.
 */
final class ArticleCategoryPolicy extends ScopedPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->authorize($user, 'content.view');
    }

    public function view(User $user, ArticleCategory $articleCategory): bool
    {
        return $this->authorize($user, 'content.view');
    }

    public function create(User $user): bool
    {
        return $this->authorize($user, 'content.create');
    }

    public function update(User $user, ArticleCategory $articleCategory): bool
    {
        return $this->authorize($user, 'content.edit');
    }

    public function delete(User $user, ArticleCategory $articleCategory): bool
    {
        return $this->authorize($user, 'content.delete');
    }
}
