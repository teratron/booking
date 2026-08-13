<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\FinancialRecord;
use App\Models\User;

/**
 * The ledger is portal-wide — a country-scoped grant governs which objects
 * an administrator may edit, not which financial rows they may read; access
 * is gated on the standalone `finance.*` permission set, never inherited
 * from object scope.
 */
final class FinancialRecordPolicy extends ScopedPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->authorize($user, 'finance.view');
    }

    public function view(User $user, FinancialRecord $financialRecord): bool
    {
        return $this->authorize($user, 'finance.view');
    }

    public function create(User $user): bool
    {
        return $this->authorize($user, 'finance.create');
    }

    public function update(User $user, FinancialRecord $financialRecord): bool
    {
        return $this->authorize($user, 'finance.edit');
    }

    public function delete(User $user, FinancialRecord $financialRecord): bool
    {
        return $this->authorize($user, 'finance.delete');
    }
}
