<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Object_;
use App\Models\User;
use App\Services\Authorization\CabinetAccessResolver;
use App\Services\Authorization\ScopeAuthorizer;

/**
 * Guards a single object against the actor's grants along either of two
 * independent axes: a staff account's country/territory/category scope
 * (admin panel), or an owner/staff-member's direct relationship to this one
 * object (owner cabinet, via {@see CabinetAccessResolver}). Neither axis
 * implies the other — a country administrator is not the object's owner, and
 * an owner has no country-scoped grant row at all — so a record-level check
 * must try both before refusing.
 *
 * The list narrowing in each panel's shared resource contract removes
 * unreachable rows from a page; this decides the record, which is the half
 * that still matters when a URL is typed rather than clicked.
 */
final class Object_Policy extends ScopedPolicy
{
    public function __construct(
        ScopeAuthorizer $authorizer,
        private readonly CabinetAccessResolver $cabinetAccess,
    ) {
        parent::__construct($authorizer);
    }

    public function viewAny(User $user): bool
    {
        return $user->can('object.view');
    }

    public function view(User $user, Object_ $object): bool
    {
        return $this->authorizeAgainst($user, 'object.view', $object);
    }

    public function create(User $user): bool
    {
        return $user->can('object.create');
    }

    public function update(User $user, Object_ $object): bool
    {
        return $this->authorizeAgainst($user, 'object.edit', $object);
    }

    public function publish(User $user, Object_ $object): bool
    {
        return $this->authorizeAgainst($user, 'object.publish', $object);
    }

    public function delete(User $user, Object_ $object): bool
    {
        return $this->authorizeAgainst($user, 'object.delete', $object);
    }

    public function restore(User $user, Object_ $object): bool
    {
        return $this->authorizeAgainst($user, 'object.delete', $object);
    }

    /**
     * Permanent deletion is not an ordinary grant. It is restricted to the
     * chief administrator regardless of what any role's permission set says,
     * because the record it removes is the one nobody can restore.
     */
    public function forceDelete(User $user, Object_ $object): bool
    {
        return $user->hasRole('chief_administrator');
    }

    public function export(User $user): bool
    {
        return $user->can('object.export');
    }

    private function authorizeAgainst(User $user, string $permission, Object_ $object): bool
    {
        if ($this->authorize(
            $user,
            $permission,
            (int) $object->country_id,
            (int) $object->territory_id,
            (int) $object->object_type_id,
        )) {
            return true;
        }

        return $this->cabinetAccess->authorize($user, $permission, $object);
    }
}
