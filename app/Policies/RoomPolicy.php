<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Object_;
use App\Models\Room;
use App\Models\User;
use App\Services\Authorization\CabinetAccessResolver;
use App\Services\Authorization\ScopeAuthorizer;

/**
 * Guards one room category by delegating to its owning object's own
 * ownership rules — a room carries no scope of its own, so every decision
 * here resolves through {@see Room::object()} and reuses exactly the two
 * axes {@see Object_Policy} already checks: a staff account's
 * country/territory/category scope, or an owner/staff-member's direct
 * relationship to the object via {@see CabinetAccessResolver}.
 *
 * Whether the object's own type even declares room support is a separate
 * concern, enforced at the resource layer (see the cabinet's own room
 * management screen) rather than here — this policy answers "may this actor
 * touch this row", never "does this kind of object have rooms at all".
 */
final class RoomPolicy extends ScopedPolicy
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

    public function view(User $user, Room $room): bool
    {
        return $this->authorizeAgainstObject($user, 'object.view', $room);
    }

    public function create(User $user): bool
    {
        return $user->can('object.edit');
    }

    public function update(User $user, Room $room): bool
    {
        return $this->authorizeAgainstObject($user, 'object.edit', $room);
    }

    public function delete(User $user, Room $room): bool
    {
        return $this->authorizeAgainstObject($user, 'object.edit', $room);
    }

    private function authorizeAgainstObject(User $user, string $permission, Room $room): bool
    {
        $object = $room->object;

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
