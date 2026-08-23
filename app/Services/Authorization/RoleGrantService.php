<?php

declare(strict_types=1);

namespace App\Services\Authorization;

use App\Exceptions\UnrevocableGrantException;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

/**
 * Guards role revocation against the one failure mode that locks every
 * administrator out of the panel that manages permissions: removing the
 * chief administrator role from its last remaining holder. Application-level
 * guards are one forgotten call away from being bypassed, so this is the
 * single path every role-revocation surface must go through — a Filament
 * bulk action or a future API endpoint that revokes roles directly against
 * the model instead of through this service reopens the lockout risk.
 *
 * The grant side exists for the symmetric reason: a role assigned through
 * Spatie's own {@see HasRoles::assignRole()}
 * directly carries a permission with no scope decision behind it at all —
 * {@see ScopeAuthorizer} then reads that as "reaches no axis" and every
 * scoped resource fails closed. A caller must choose a scope, not omit one
 * by using the wrong entry point.
 */
final class RoleGrantService
{
    private const string UNREVOCABLE_ROLE = 'chief_administrator';

    /**
     * Assigns $roleName to $user and records the scope the grant is bounded
     * to, in one call — the two are never allowed to drift apart, since a
     * role with no matching {@see ScopeAuthorizer}-readable scope row is
     * indistinguishable from a role with no permissions at all on any
     * resource that carries a country, territory, or category axis.
     *
     * @param  'none'|'country'|'territory'|'category'  $scopeKind  'none' grants
     *                                                              an unrestricted role, matching {@see ScopeConstraint::unrestricted()}
     * @param  int|null  $scopeReferenceId  required when $scopeKind is not 'none';
     *                                      ignored (and stored null) otherwise
     */
    public function grantRole(
        User $user,
        string $roleName,
        User $grantedBy,
        string $scopeKind = 'none',
        ?int $scopeReferenceId = null,
    ): void {
        $role = Role::query()->where('name', $roleName)->firstOrFail();

        DB::transaction(function () use ($user, $role, $grantedBy, $scopeKind, $scopeReferenceId): void {
            $user->assignRole($role);

            DB::table('role_scopes')->insert([
                'user_id' => $user->id,
                'role_id' => $role->id,
                'scope_kind' => $scopeKind,
                'scope_reference_id' => $scopeKind === 'none' ? null : $scopeReferenceId,
                'granted_by' => $grantedBy->id,
                'granted_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    /**
     * Revoke a role from a user.
     *
     * @throws UnrevocableGrantException if $roleName is the chief
     *                                   administrator role and $user is its last remaining holder — the
     *                                   role is never left with zero holders through this path.
     */
    public function revokeRole(User $user, string $roleName): void
    {
        if ($roleName === self::UNREVOCABLE_ROLE && $this->isLastHolder($user, $roleName)) {
            throw new UnrevocableGrantException(
                "Cannot revoke '{$roleName}' from its last remaining holder — the panel ".
                'that manages permissions would become inaccessible to every administrator.'
            );
        }

        $user->removeRole($roleName);
    }

    private function isLastHolder(User $user, string $roleName): bool
    {
        return $user->hasRole($roleName) && User::role($roleName)->count() === 1;
    }
}
