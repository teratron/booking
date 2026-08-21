<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ApiToken;
use App\Models\User;

/**
 * A token is managed entirely under its owning {@see ApiClient}'s own
 * permission — issuing, re-scoping, and revoking all check `update` on the
 * client record, not a separate grant of their own (see
 * `TokensRelationManager`'s action-level authorization). This policy exists
 * so Filament's strict authorization mode, which requires every model a
 * panel renders to declare one, has something to find; it mirrors
 * {@see ApiClientPolicy} exactly rather than inventing a second scope
 * reading for what is really one resource's sub-record.
 */
final class ApiTokenPolicy extends ScopedPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->authorize($user, 'api.view');
    }

    public function view(User $user, ApiToken $apiToken): bool
    {
        return $this->authorize($user, 'api.view');
    }

    public function create(User $user): bool
    {
        return $this->authorize($user, 'api.create');
    }

    public function update(User $user, ApiToken $apiToken): bool
    {
        return $this->authorize($user, 'api.edit');
    }

    public function delete(User $user, ApiToken $apiToken): bool
    {
        return $this->authorize($user, 'api.delete');
    }
}
