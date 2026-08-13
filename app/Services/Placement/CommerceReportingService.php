<?php

declare(strict_types=1);

namespace App\Services\Placement;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The report set §5.5 and §61/§123 name for the back office: active
 * placements, placements ending at each warning threshold, expired
 * placements, objects with no term set, free placements, paid bump counts,
 * and active advertising campaigns. Each figure is a real query against
 * seeded volume — cheap enough to compute on demand, so nothing here is
 * cached; the ledger export (a separate, filtered concern) is what a staff
 * member reaches for when the raw rows matter.
 *
 * @phpstan-type CommerceFilters array{country?: int, period_from?: string, period_until?: string, placement_package_id?: int, staff_id?: int}
 */
final class CommerceReportingService
{
    /**
     * @param  CommerceFilters  $filters
     * @return array<string, int>
     */
    public function summary(array $filters = []): array
    {
        return [
            'active_placements' => $this->activePlacements($filters),
            'ending_30' => $this->endingWithin(30, $filters),
            'ending_14' => $this->endingWithin(14, $filters),
            'ending_7' => $this->endingWithin(7, $filters),
            'ending_3' => $this->endingWithin(3, $filters),
            'expired_placements' => $this->expiredPlacements($filters),
            'no_term_set' => $this->noTermSet(),
            'free_placements' => $this->freePlacements($filters),
            'paid_bump_count' => $this->paidBumpCount($filters),
            'active_campaigns' => $this->activeCampaigns(),
        ];
    }

    /** @param  CommerceFilters  $filters */
    private function activePlacements(array $filters): int
    {
        return $this->scopedPlacements($filters)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->count();
    }

    /** @param  CommerceFilters  $filters */
    private function endingWithin(int $days, array $filters): int
    {
        return $this->scopedPlacements($filters)
            ->whereBetween('ends_at', [now()->toDateString(), now()->addDays($days)->toDateString()])
            ->count();
    }

    /** @param  CommerceFilters  $filters */
    private function expiredPlacements(array $filters): int
    {
        return $this->scopedPlacements($filters)
            ->where('ends_at', '<', now()->toDateString())
            ->count();
    }

    private function noTermSet(): int
    {
        return DB::table('objects')
            ->whereNull('deleted_at')
            ->whereNotExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('object_placements')
                    ->whereColumn('object_placements.object_id', 'objects.id');
            })
            ->count();
    }

    /** @param  CommerceFilters  $filters */
    private function freePlacements(array $filters): int
    {
        return $this->scopedPlacements($filters)
            ->join('placement_packages', 'placement_packages.id', '=', 'object_placements.placement_package_id')
            ->where('placement_packages.price', 0)
            ->count();
    }

    /** @param  CommerceFilters  $filters */
    private function paidBumpCount(array $filters): int
    {
        $query = DB::table('bump_events')->where('type', 'paid');

        if (isset($filters['period_from'])) {
            $query->where('occurred_at', '>=', $filters['period_from']);
        }

        if (isset($filters['period_until'])) {
            $query->where('occurred_at', '<=', $filters['period_until']);
        }

        return $query->count();
    }

    private function activeCampaigns(): int
    {
        if (! DB::getSchemaBuilder()->hasTable('banners')) {
            return 0;
        }

        return DB::table('banners')
            ->where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->count();
    }

    /** @param  CommerceFilters  $filters */
    private function scopedPlacements(array $filters): Builder
    {
        $query = DB::table('object_placements');

        if (isset($filters['placement_package_id'])) {
            $query->where('object_placements.placement_package_id', $filters['placement_package_id']);
        }

        if (isset($filters['country'])) {
            $query->join('objects', 'objects.id', '=', 'object_placements.object_id')
                ->where('objects.country_id', $filters['country']);
        }

        return $query;
    }
}
