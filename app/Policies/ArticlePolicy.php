<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Article;
use App\Models\User;

/**
 * Articles are administrator-authored only — there is no owner to scope
 * against, so unlike `Object_Policy`'s country/territory/category triple,
 * every ability here resolves against an unrestricted `content` grant alone.
 */
final class ArticlePolicy extends ScopedPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->authorize($user, 'content.view');
    }

    public function view(User $user, Article $article): bool
    {
        return $this->authorize($user, 'content.view');
    }

    public function create(User $user): bool
    {
        return $this->authorize($user, 'content.create');
    }

    public function update(User $user, Article $article): bool
    {
        return $this->authorize($user, 'content.edit');
    }

    public function publish(User $user, Article $article): bool
    {
        return $this->authorize($user, 'content.publish');
    }

    public function delete(User $user, Article $article): bool
    {
        return $this->authorize($user, 'content.delete');
    }

    public function restore(User $user, Article $article): bool
    {
        return $this->authorize($user, 'content.delete');
    }
}
