<?php

declare(strict_types=1);

namespace App\Services\Authorization;

use App\Models\Country;
use App\Models\ObjectType;
use App\Models\RoleScope;
use App\Models\Territory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use stdClass;

/**
 * Turns a stored role grant into the human-readable line the staff-
 * administration screen shows — "Country administrator (Moldova)" rather
 * than a role key and a bare numeric scope reference. The specification's
 * "what a role can do is readable before it is granted" only holds once an
 * administrator can actually read it, not merely query the tables by hand.
 *
 * Role display names and scope-target names are memoized per instance —
 * bound as a singleton (see `AppServiceProvider`) so that memo actually
 * spans the whole request: the staff list renders one of these lines per
 * row via a Filament column closure, and a fresh instance per row would
 * make the memo pointless. `grantLines()` itself reads `$user->roleScopes`
 * rather than issuing its own query — `StaffResource::$eagerLoad` loads
 * that relation once for the whole page, and a query here per row would
 * silently reintroduce the N+1 that eager load exists to prevent.
 */
final class RoleGrantPresenter
{
    /** @var array<string, string> */
    private array $roleNames = [];

    /** @var array<string, string> */
    private array $scopeTargetNames = [];

    /** @return list<string> one line per grant $user currently holds, revoked grants excluded */
    public function activeGrantLines(User $user): array
    {
        return $this->grantLines($user, onlyActive: true);
    }

    /** @return list<string> one line per grant $user has ever held, revoked ones marked as such */
    public function allGrantLines(User $user): array
    {
        return $this->grantLines($user, onlyActive: false);
    }

    /**
     * The role options a grant picker offers, keyed by role id with the
     * same translated display name {@see roleName()} resolves — a role
     * select built from the raw key would ask an administrator to
     * recognise `country_administrator` rather than read its own name.
     *
     * @param  list<string>  $excludedRoleKeys  role keys never offered, e.g. the object-side roles a staff screen must not grant
     * @return array<int, string>
     */
    public function roleOptions(array $excludedRoleKeys = []): array
    {
        $roles = DB::table('roles')
            ->whereNotIn('name', $excludedRoleKeys)
            ->orderBy('name')
            ->get(['id', 'name']);

        return $roles->mapWithKeys(fn (stdClass $role): array => [
            (int) $role->id => $this->roleName((int) $role->id, (string) $role->name),
        ])->all();
    }

    /**
     * Reads `$user->roleScopes` — already eager-loaded with its `role` on
     * the staff list's own query — rather than a fresh join, so this never
     * costs a query of its own no matter how many rows the list renders.
     *
     * @return list<string>
     */
    private function grantLines(User $user, bool $onlyActive): array
    {
        $scopes = $user->roleScopes
            ->when($onlyActive, fn ($scopes) => $scopes->whereNull('revoked_at'))
            ->sortBy(fn (RoleScope $scope): string => $scope->role instanceof Role ? $scope->role->name : '');

        $lines = [];

        foreach ($scopes as $scope) {
            $roleKey = $scope->role instanceof Role ? $scope->role->name : (string) $scope->role_id;
            $label = $this->roleName($scope->role_id, $roleKey).
                ' ('.$this->scopeDescription($scope->scope_kind, $scope->scope_reference_id).')';

            $lines[] = $scope->revoked_at !== null
                ? (string) __('panel.staff.grants.revoked_line', ['grant' => $label])
                : $label;
        }

        return $lines;
    }

    /**
     * The translated display name for a role, falling back to its raw key
     * when the current locale has no translation row. Public: the staff
     * grant-history table reads this directly per row, alongside
     * {@see grantLines()}'s own use of it when composing a full line.
     */
    public function roleName(int $roleId, string $roleKey): string
    {
        return $this->roleNames[$roleKey] ??= (string) (DB::table('role_translations')
            ->where('role_id', $roleId)
            ->where('locale', app()->getLocale())
            ->value('display_name') ?? $roleKey);
    }

    /**
     * The human-readable description of a scope kind and reference — "None",
     * a country's code, or a territory's/category's translated name. Public
     * for the same reason {@see roleName()} is.
     */
    public function scopeDescription(string $scopeKind, ?int $referenceId): string
    {
        if ($scopeKind === 'none' || $referenceId === null) {
            return (string) __('panel.staff.scope.none');
        }

        $cacheKey = "{$scopeKind}:{$referenceId}";

        // A deleted scope target is a real state — the specification
        // records it as a suspended grant that stays visible rather than
        // silently disappearing or widening. This presenter only renders
        // that case as a missing-target label; it does not (yet) track or
        // surface the suspension itself.
        $missing = (string) __('panel.staff.scope.missing_target');

        return $this->scopeTargetNames[$cacheKey] ??= match ($scopeKind) {
            'country' => ($country = Country::query()->find($referenceId)) !== null ? $country->code : $missing,
            'territory' => ($territory = Territory::query()->find($referenceId)) !== null ? ($territory->name ?? $missing) : $missing,
            'category' => ($category = ObjectType::query()->find($referenceId)) !== null ? ($category->name ?? $missing) : $missing,
            default => $missing,
        };
    }
}
