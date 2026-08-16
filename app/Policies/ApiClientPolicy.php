<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ApiClient;
use App\Models\User;

/**
 * API clients and their tokens are portal-wide administration, not scoped to
 * a country, territory, or category — the same reasoning
 * {@see ModulePolicy} applies to the module registry.
 */
final class ApiClientPolicy extends ScopedPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->authorize($user, 'api.view');
    }

    public function view(User $user, ApiClient $apiClient): bool
    {
        return $this->authorize($user, 'api.view');
    }

    public function create(User $user): bool
    {
        return $this->authorize($user, 'api.create');
    }

    public function update(User $user, ApiClient $apiClient): bool
    {
        return $this->authorize($user, 'api.edit');
    }

    public function delete(User $user, ApiClient $apiClient): bool
    {
        return $this->authorize($user, 'api.delete');
    }
}
