<?php

declare(strict_types=1);

namespace App\Services\Authorization;

use App\Models\Object_;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Resolves whether a user may act on one object through the owner cabinet —
 * an axis entirely separate from {@see ScopeAuthorizer}'s country/territory/
 * category grants, which govern staff acting through the admin panel.
 *
 * Two distinct paths exist, per the `object_user` migration's own design
 * intent:
 *
 * - **The owner** (`objects.owner_id === $user->id`) carries whatever the
 *   `object_owner` role's `spatie/laravel-permission` grants allow —
 *   ownership itself is not a permission, it is what makes the permission
 *   check apply to this particular object rather than every object.
 * - **A staff member** attached via `object_user` is deliberately NOT routed
 *   through `spatie/laravel-permission` at all: that pivot's own `permissions`
 *   JSON column is a self-contained, per-object grant list, evaluated
 *   independently of whatever portal-wide role the account holds. A row with
 *   no matching key, or no row at all, grants nothing.
 *
 * Memoized per (user, object, permission) for the lifetime of the request —
 * the same repeated-query risk `ScopeAuthorizer` was fixed for once already;
 * cheap insurance against a resource's Policy check and its list-query
 * narrowing asking the same question twice.
 */
final class CabinetAccessResolver
{
    /** @var array<string, bool> */
    private array $memo = [];

    public function authorize(User $user, string $permission, Object_ $object): bool
    {
        $key = "{$user->id}:{$object->id}:{$permission}";

        if (array_key_exists($key, $this->memo)) {
            return $this->memo[$key];
        }

        return $this->memo[$key] = ((int) $object->owner_id === $user->id)
            ? $user->can($permission)
            : $this->staffGrantCovers($user, $object, $permission);
    }

    private function staffGrantCovers(User $user, Object_ $object, string $permission): bool
    {
        /** @var ?string $raw */
        $raw = DB::table('object_user')
            ->where('object_id', $object->id)
            ->where('user_id', $user->id)
            ->value('permissions');

        if ($raw === null) {
            return false;
        }

        /** @var array<string, bool> $grants */
        $grants = json_decode($raw, true) ?? [];

        return (bool) ($grants[$permission] ?? false);
    }
}
