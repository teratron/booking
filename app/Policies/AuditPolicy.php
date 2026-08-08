<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use OwenIt\Auditing\Models\Audit;

/**
 * The action journal is portal-wide and unscoped — a country-scoped
 * administrator's grant governs the records they may change, not the
 * journal of every change across the portal, which is why `audit.view` is
 * its own standalone permission rather than a per-resource verb. Bound via
 * `Gate::policy()` in `AppServiceProvider`, not a `#[UsePolicy]` attribute,
 * since `Audit` is a vendor model this project does not own.
 */
final class AuditPolicy extends ScopedPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('audit.view');
    }

    public function view(User $user, Audit $audit): bool
    {
        return $user->can('audit.view');
    }
}
