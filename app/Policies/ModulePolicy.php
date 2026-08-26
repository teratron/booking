<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Module;
use App\Models\User;

/**
 * The registry is portal-wide configuration, so no scope axis narrows it —
 * every method resolves through the unrestricted branch of the shared
 * authorizer. Membership is fixed by the code that implements each module, so
 * create and delete are denied outright rather than left to a permission.
 *
 * Gated on `system.*`, not `settings.*` — a module toggle can enable or
 * disable payment, booking, or the public API portal-wide, a duty distinct
 * from the SEO-adjacent registries (`ObjectTypePolicy`, `RedirectPolicy`)
 * `settings.*` still covers. A role once held `settings.edit` for the
 * object-type registry's own SEO fields and, as an unintended side effect,
 * could switch the payment module off for every object on the portal.
 */
final class ModulePolicy extends ScopedPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->authorize($user, 'system.view');
    }

    public function view(User $user, Module $module): bool
    {
        return $this->authorize($user, 'system.view');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Module $module): bool
    {
        return $this->authorize($user, 'system.edit');
    }

    public function delete(User $user, Module $module): bool
    {
        return false;
    }
}
