<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Object_;
use App\Models\ObjectPhoto;
use App\Models\User;
use App\Services\Authorization\CabinetAccessResolver;
use App\Services\Authorization\ScopeAuthorizer;

/**
 * Guards one uploaded photograph by delegating to its owning object's own
 * ownership rules — a photo carries no scope of its own, so every decision
 * here resolves through {@see ObjectPhoto::object()} and reuses exactly the
 * two axes {@see Object_Policy} already checks: a staff account's
 * country/territory/category scope, or an owner/staff-member's direct
 * relationship to the object via {@see CabinetAccessResolver}.
 */
final class ObjectPhotoPolicy extends ScopedPolicy
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

    public function view(User $user, ObjectPhoto $photo): bool
    {
        return $this->authorizeAgainstObject($user, 'object.view', $photo);
    }

    public function create(User $user): bool
    {
        return $user->can('object.edit');
    }

    public function update(User $user, ObjectPhoto $photo): bool
    {
        return $this->authorizeAgainstObject($user, 'object.edit', $photo);
    }

    public function delete(User $user, ObjectPhoto $photo): bool
    {
        return $this->authorizeAgainstObject($user, 'object.edit', $photo);
    }

    /**
     * The table's drag-to-reorder feature is authorized at the class level
     * (Filament's own `canReorder()` never receives a record to check
     * against), so this cannot re-run {@see self::authorizeAgainstObject()}
     * the way every other action here does. That is not a gap: the table's
     * own query is already narrowed to the current tenant object by Filament's
     * tenancy scope before a single row reaches the drag handles, so the
     * blanket `object.edit` permission this checks is exactly the same gate
     * {@see self::create()} already uses for the same reason.
     */
    public function reorder(User $user): bool
    {
        return $user->can('object.edit');
    }

    private function authorizeAgainstObject(User $user, string $permission, ObjectPhoto $photo): bool
    {
        $object = $photo->object;

        if (! $object instanceof Object_) {
            return false;
        }

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
