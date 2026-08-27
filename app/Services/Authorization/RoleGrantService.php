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
        $referenceId = $scopeKind === 'none' ? null : $scopeReferenceId;

        DB::transaction(function () use ($user, $role, $grantedBy, $scopeKind, $referenceId): void {
            $user->assignRole($role);

            $matcher = [
                'user_id' => $user->id,
                'role_id' => $role->id,
                'scope_kind' => $scopeKind,
                'scope_reference_id' => $referenceId,
            ];

            $existingId = DB::table('role_scopes')->where($matcher)->value('id');

            if ($existingId !== null) {
                // The same role/scope combination the account held before,
                // most often re-granted right after a revocation. Updating
                // the existing row un-revokes it; inserting a second row for
                // the identical combination would violate the table's own
                // uniqueness guarantee.
                DB::table('role_scopes')->where('id', $existingId)->update([
                    'granted_by' => $grantedBy->id,
                    'granted_at' => now(),
                    'revoked_by' => null,
                    'revoked_at' => null,
                    'updated_at' => now(),
                ]);

                return;
            }

            DB::table('role_scopes')->insert([
                ...$matcher,
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
     * The matching `role_scopes` row (or rows — a user may hold the same
     * role under several distinct scopes) is marked with $revokedBy and the
     * current time rather than deleted: the back-office specification
     * requires a revocation to be "recorded with actor and time", and a
     * deleted row carries neither.
     *
     * @throws UnrevocableGrantException if $roleName is the chief
     *                                   administrator role and $user is its last remaining holder — the
     *                                   role is never left with zero holders through this path.
     */
    public function revokeRole(User $user, string $roleName, User $revokedBy): void
    {
        if ($roleName === self::UNREVOCABLE_ROLE && $this->isLastHolder($user, $roleName)) {
            throw new UnrevocableGrantException(
                "Cannot revoke '{$roleName}' from its last remaining holder — the panel ".
                'that manages permissions would become inaccessible to every administrator.'
            );
        }

        $role = Role::query()->where('name', $roleName)->firstOrFail();

        DB::transaction(function () use ($user, $role, $revokedBy): void {
            $user->removeRole($role);

            DB::table('role_scopes')
                ->where('user_id', $user->id)
                ->where('role_id', $role->id)
                ->whereNull('revoked_at')
                ->update([
                    'revoked_by' => $revokedBy->id,
                    'revoked_at' => now(),
                    'updated_at' => now(),
                ]);
        });
    }

    /**
     * Guards deactivating a staff account the same way {@see revokeRole()}
     * guards revoking the role outright — refusing the account's own panel
     * access is the same lockout by a different route when $user is the
     * chief administrator's last remaining holder able to actually sign in.
     * A co-holder who is already deactivated does not count: it cannot
     * administer the panel either, so it offers no real recovery path.
     *
     * @throws UnrevocableGrantException if deactivating $user would leave
     *                                   the chief administrator role with no holder able to sign in
     */
    public function guardDeactivation(User $user): void
    {
        if (! $user->hasRole(self::UNREVOCABLE_ROLE)) {
            return;
        }

        $hasAnotherActiveHolder = User::role(self::UNREVOCABLE_ROLE)
            ->where('id', '!=', $user->id)
            ->whereNull('blocked_at')
            ->exists();

        if (! $hasAnotherActiveHolder) {
            throw new UnrevocableGrantException(
                "Cannot deactivate the last active holder of '".self::UNREVOCABLE_ROLE."' — the panel ".
                'that manages permissions would become inaccessible to every administrator.'
            );
        }
    }

    private function isLastHolder(User $user, string $roleName): bool
    {
        return $user->hasRole($roleName) && User::role($roleName)->count() === 1;
    }
}
