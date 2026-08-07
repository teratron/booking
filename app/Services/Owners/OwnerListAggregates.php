<?php

declare(strict_types=1);

namespace App\Services\Owners;

use App\Models\Object_;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use OwenIt\Auditing\Models\Audit;

/**
 * Adds the owner list's read-only aggregates to a query over `User`: how many
 * objects an owner holds, how many of their placements have lapsed, and when
 * they last signed in.
 *
 * Kept here rather than inline in the table definition: the panel layer
 * reaches the database only through a service, never through the query
 * builder facade directly, and this is exactly the kind of raw aggregate a
 * Filament resource is not allowed to build for itself.
 */
final class OwnerListAggregates
{
    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function apply(Builder $query): Builder
    {
        return $query
            // A back-office count must include an owner's pending and
            // rejected objects too, not only the ones a public page would
            // show — `withUnmoderated()` is the moderation scope's own
            // escape hatch for exactly this kind of administrative read.
            ->withCount(['objects as objects_count' => $this->unmoderatedObjects(...)])
            ->addSelect([
                'last_sign_in_at' => Audit::query()
                    ->selectRaw('max(created_at)')
                    ->whereColumn('auditable_id', 'users.id')
                    ->where('auditable_type', User::class)
                    ->where('event', 'sign_in'),
                'overdue_placements_count' => DB::table('object_placements')
                    ->join('objects', 'objects.id', '=', 'object_placements.object_id')
                    ->whereColumn('objects.owner_id', 'users.id')
                    ->whereNull('objects.deleted_at')
                    ->where('object_placements.ends_at', '<', now())
                    ->selectRaw('count(*)'),
            ]);
    }

    /**
     * @param  Builder<Object_>  $objects
     * @return Builder<Object_>
     */
    private function unmoderatedObjects(Builder $objects): Builder
    {
        return $objects->withUnmoderated();
    }
}
