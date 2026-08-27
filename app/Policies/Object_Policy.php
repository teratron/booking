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

    /**
     * A merge permanently removes the merged-away record's own identity —
     * the same class of action `object.delete` already gates, so a merge
     * shares that grant rather than introducing a permission nothing else
     * checks. The caller checks this ability against *both* records in a
     * pair before merging either.
     */
    public function merge(User $user, Object_ $object): bool
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

    /**
     * Conferring an existing placement package on an object, and setting or
     * clearing its manual position within its own tier — an ordinary,
     * scopable administrator permission (`[TZ]` §112 grants every position
     * operation to a generic "administrator"), separate from creating and
     * pricing the packages themselves. Reuses the `commerce` resource's own
     * `edit` verb rather than a placement-specific permission key —
     * granting is a commerce action on an object the same way editing its
     * price-bearing fields is.
     *
     * `[TZ]` §25.2 separately reserves one narrower thing to the chief
     * administrator alone: a manual position that would let a lower tier
     * outrank a higher one in the same scope. This method does not attempt
     * to detect that case — doing so correctly needs the current catalog
     * ordering at the object's own scope, not just the object in isolation
     * — so every position change this policy gates is treated as the
     * ordinary, scoped kind for now. Distinguishing the cross-tier override
     * is deferred to a follow-up task rather than guessed at here.
     */
    public function grantPlacement(User $user, Object_ $object): bool
    {
        return $this->authorizeAgainst($user, 'commerce.edit', $object);
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
