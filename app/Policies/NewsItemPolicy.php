<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\NewsItem;
use App\Models\User;

/**
 * A news item carries only a territory scope of its own (`territory_id`,
 * nullable — a portal-wide item has none) — no country or category column
 * exists on the table to narrow against, unlike `Object_Policy`'s triple.
 */
final class NewsItemPolicy extends ScopedPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('content.view');
    }

    public function view(User $user, NewsItem $newsItem): bool
    {
        return $this->authorizeAgainst($user, 'content.view', $newsItem);
    }

    public function create(User $user): bool
    {
        return $user->can('content.create');
    }

    public function update(User $user, NewsItem $newsItem): bool
    {
        return $this->authorizeAgainst($user, 'content.edit', $newsItem);
    }

    public function publish(User $user, NewsItem $newsItem): bool
    {
        return $this->authorizeAgainst($user, 'content.publish', $newsItem);
    }

    public function delete(User $user, NewsItem $newsItem): bool
    {
        return $this->authorizeAgainst($user, 'content.delete', $newsItem);
    }

    public function restore(User $user, NewsItem $newsItem): bool
    {
        return $this->authorizeAgainst($user, 'content.delete', $newsItem);
    }

    private function authorizeAgainst(User $user, string $permission, NewsItem $newsItem): bool
    {
        return $this->authorize($user, $permission, null, $newsItem->territory_id, null);
    }
}
