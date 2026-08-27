<?php

declare(strict_types=1);

namespace App\Services\Staff;

use App\Models\User;
use App\Services\Owners\OwnerListAggregates;
use Illuminate\Database\Eloquent\Builder;
use OwenIt\Auditing\Models\Audit;

/**
 * Adds the staff list's one read-only aggregate to a query over `User`: when
 * the account last signed in. Mirrors
 * {@see OwnerListAggregates}'s `last_sign_in_at`
 * subquery exactly — sign-in is journalled the same way for every account
 * regardless of which panel it reaches, so the same audit-log read serves
 * both lists.
 *
 * Kept here rather than inline in the table definition: the panel layer
 * reaches the database only through a service, never through the query
 * builder facade directly.
 */
final class StaffListAggregates
{
    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function apply(Builder $query): Builder
    {
        return $query->addSelect([
            'last_sign_in_at' => Audit::query()
                ->selectRaw('max(created_at)')
                ->whereColumn('auditable_id', 'users.id')
                ->where('auditable_type', User::class)
                ->where('event', 'sign_in'),
        ]);
    }
}
