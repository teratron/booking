<?php

declare(strict_types=1);

namespace App\Services\Authorization;

use App\Exceptions\UnrevocableGrantException;
use App\Models\User;

/**
 * Guards role revocation against the one failure mode that locks every
 * administrator out of the panel that manages permissions: removing the
 * chief administrator role from its last remaining holder. Application-level
 * guards are one forgotten call away from being bypassed, so this is the
 * single path every role-revocation surface must go through — a Filament
 * bulk action or a future API endpoint that revokes roles directly against
 * the model instead of through this service reopens the lockout risk.
 */
final class RoleGrantService
{
    private const string UNREVOCABLE_ROLE = 'chief_administrator';

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
