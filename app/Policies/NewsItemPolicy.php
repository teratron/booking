<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\NewsItem;
use App\Models\Object_;
use App\Models\User;
use App\Services\Authorization\CabinetAccessResolver;
use App\Services\Authorization\ScopeAuthorizer;

/**
 * A news item carries only a territory scope of its own (`territory_id`,
 * nullable — a portal-wide item has none) — no country or category column
 * exists on the table to narrow against, unlike `Object_Policy`'s triple.
 *
 * A news item authored through the owner cabinet has no `role_scopes` grant
 * to resolve against at all — an owner's authority comes from owning the
 * object the item is attributed to, not from a staff scope row — so every
 * check here also falls back to {@see CabinetAccessResolver}, mirroring
 * {@see ObjectPhotoPolicy}'s identical two-axis shape for the same reason. A
 * portal-wide item (`object_id` null) has no object to fall back through and
 * is a staff-only concern either way.
 */
final class NewsItemPolicy extends ScopedPolicy
{
    public function __construct(
        ScopeAuthorizer $authorizer,
        private readonly CabinetAccessResolver $cabinetAccess,
    ) {
        parent::__construct($authorizer);
    }

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
        if ($this->authorize($user, $permission, null, $newsItem->territory_id, null)) {
            return true;
        }

        $object = $newsItem->object;

        return $object instanceof Object_ && $this->cabinetAccess->authorize($user, $permission, $object);
    }
}
