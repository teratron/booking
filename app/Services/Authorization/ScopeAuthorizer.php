<?php

declare(strict_types=1);

namespace App\Services\Authorization;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The single server-side check every scoped authorization decision in this
 * project goes through. `spatie/laravel-permission` answers "does this user
 * hold a role with this permission at all"; a grant may additionally be
 * bounded to a country, a territory subtree, or an object category, which
 * spatie's own tables have no concept of — that narrowing is resolved here,
 * against `role_scopes`, and nowhere else. Per-screen checks diverge, and
 * the divergence surfaces as a country administrator editing another
 * country's data — a failure with no visible symptom until it matters.
 *
 * Only role-based grants are resolved: this project assigns permissions to
 * users exclusively through roles (`role_scopes` is keyed to a role grant),
 * so a permission assigned directly to a user, bypassing every role, has no
 * scope record to resolve against and is treated as having none.
 */
final class ScopeAuthorizer
{
    /**
     * Whether $user's role grants of $permission cover a target described by
     * the given scope values. Pass only the values relevant to the target —
     * an omitted axis never matches a grant scoped to that axis, since a
     * target with no category, for instance, cannot fall within a
     * category-scoped grant. A target axis a grant needs but the caller
     * left null resolves to "does not cover", never an exception.
     */
    public function authorize(
        User $user,
        string $permission,
        ?int $countryId = null,
        ?int $territoryId = null,
        ?int $categoryId = null,
    ): bool {
        if (! $user->can($permission)) {
            return false;
        }

        $roleIds = $user->roles()
            ->whereHas('permissions', fn ($query) => $query->where('name', $permission))
            ->pluck('id');

        if ($roleIds->isEmpty()) {
            return false;
        }

        $scopes = DB::table('role_scopes')
            ->where('user_id', $user->id)
            ->whereIn('role_id', $roleIds)
            ->get(['scope_kind', 'scope_reference_id']);

        foreach ($scopes as $scope) {
            if ($this->scopeCovers($scope->scope_kind, $scope->scope_reference_id, $countryId, $territoryId, $categoryId)) {
                return true;
            }
        }

        return false;
    }

    private function scopeCovers(
        string $scopeKind,
        ?int $scopeReferenceId,
        ?int $countryId,
        ?int $territoryId,
        ?int $categoryId,
    ): bool {
        return match ($scopeKind) {
            'none' => true,
            'country' => $countryId !== null && $countryId === $scopeReferenceId,
            'territory' => $territoryId !== null && $scopeReferenceId !== null
                && $this->territoryWithinSubtree($territoryId, $scopeReferenceId),
            'category' => $categoryId !== null && $categoryId === $scopeReferenceId,
            default => false,
        };
    }

    /**
     * Whether $territoryId is $rootTerritoryId itself or a descendant of it,
     * walking `territories.parent_id` through a recursive CTE — a region
     * administrator's grant on a region must cover every city and resort
     * beneath it, at any depth, not just its direct children.
     */
    private function territoryWithinSubtree(int $territoryId, int $rootTerritoryId): bool
    {
        if ($territoryId === $rootTerritoryId) {
            return true;
        }

        $result = DB::selectOne(<<<'SQL'
            with recursive subtree as (
                select id from territories where id = ?
                union all
                select t.id from territories t inner join subtree s on t.parent_id = s.id
            )
            select exists(select 1 from subtree where id = ?) as covered
            SQL, [$rootTerritoryId, $territoryId]);

        return (bool) $result->covered;
    }
}
