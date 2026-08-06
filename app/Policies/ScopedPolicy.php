<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Services\Authorization\ScopeAuthorizer;

/**
 * The foundation every concrete resource Policy extends. It exists so scope
 * enforcement is one method, called the same way everywhere, rather than a
 * convention every Policy author has to remember to repeat — per-screen
 * checks diverge, and a country administrator editing another country's
 * object is exactly the divergence that costs nothing to introduce and
 * everything to notice.
 *
 * A concrete Policy's decision must derive entirely from the acting user,
 * the target's own scope attributes, and the stored grant — never from a
 * boolean the caller passes in, which would let a call site silently opt
 * itself out of the check this class exists to make uniform.
 */
abstract class ScopedPolicy
{
    public function __construct(
        protected readonly ScopeAuthorizer $authorizer,
    ) {}

    /**
     * Resolves whether $user holds a role granting $permission whose scope
     * — unrestricted, or bounded to a country, a territory subtree, or an
     * object category — covers a target described by the given values.
     */
    protected function authorize(
        User $user,
        string $permission,
        ?int $countryId = null,
        ?int $territoryId = null,
        ?int $categoryId = null,
    ): bool {
        return $this->authorizer->authorize($user, $permission, $countryId, $territoryId, $categoryId);
    }
}
