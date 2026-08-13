<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ArticleTag;
use App\Models\User;

/**
 * Tags are portal-wide configuration — no scope axis narrows this registry,
 * so only an unrestricted `content` grant reaches it.
 */
final class ArticleTagPolicy extends ScopedPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->authorize($user, 'content.view');
    }

    public function view(User $user, ArticleTag $articleTag): bool
    {
        return $this->authorize($user, 'content.view');
    }

    public function create(User $user): bool
    {
        return $this->authorize($user, 'content.create');
    }

    public function update(User $user, ArticleTag $articleTag): bool
    {
        return $this->authorize($user, 'content.edit');
    }

    public function delete(User $user, ArticleTag $articleTag): bool
    {
        return $this->authorize($user, 'content.delete');
    }
}
