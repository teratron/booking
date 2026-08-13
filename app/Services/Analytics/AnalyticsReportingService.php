<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Models\Banner;
use App\Models\Object_;
use App\Models\StatDaily;
use App\Support\Analytics\StatEventKind;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Reads the aggregate tier — every figure here comes from `stat_dailies`,
 * never from raw `stat_events`, so a report stays cheap regardless of how
 * much history retention keeps around.
 *
 * @phpstan-type AnalyticsFilters array{
 *     period_from?: string,
 *     period_until?: string,
 *     object_id?: int,
 *     banner_id?: int,
 *     territory_id?: int,
 *     country_id?: int,
 *     object_type_id?: int,
 *     locale?: string,
 * }
 */
final class AnalyticsReportingService
{
    /**
     * One count per {@see StatEventKind}, summed over whichever
     * `stat_dailies` rows `$filters` narrows down to.
     *
     * @param  AnalyticsFilters  $filters
     * @return array<string, int>
     */
    public function summary(array $filters = []): array
    {
        return $this->countsByKind($this->scopedQuery($filters));
    }

    /**
     * The same figures as {@see self::summary()}, restricted to exactly one
     * owner's own objects. No back-office UI reaches this — the owner
     * cabinet's own dashboard calls it directly, scoped to whoever is
     * signed in, never to an owner id a caller supplies from outside.
     *
     * @param  AnalyticsFilters  $filters
     * @return array<string, int>
     */
    public function forOwner(int $ownerId, array $filters = []): array
    {
        $ownedObjectIds = Object_::query()->withUnmoderated()->where('owner_id', $ownerId)->select('id');

        $query = $this->scopedQuery($filters)
            ->where('subject_type', Object_::class)
            ->whereIn('subject_id', $ownedObjectIds);

        return $this->countsByKind($query);
    }

    /**
     * The filtered, unaggregated row set — what the back-office report
     * table renders and what its export action ships. Eloquent rather than
     * the query builder deliberately: this is the same builder a Filament
     * `Table` needs for its own `->query()`.
     *
     * @param  AnalyticsFilters  $filters
     * @return Builder<StatDaily>
     */
    public function scopedQuery(array $filters = []): Builder
    {
        return StatDaily::query()
            ->when($filters['period_from'] ?? null, fn (Builder $q, string $v): Builder => $q->where('date', '>=', $v))
            ->when($filters['period_until'] ?? null, fn (Builder $q, string $v): Builder => $q->where('date', '<=', $v))
            ->when($filters['object_id'] ?? null, fn (Builder $q, int $v): Builder => $q
                ->where('subject_type', Object_::class)->where('subject_id', $v))
            ->when($filters['banner_id'] ?? null, fn (Builder $q, int $v): Builder => $q
                ->where('subject_type', Banner::class)->where('subject_id', $v))
            ->when($filters['territory_id'] ?? null, fn (Builder $q, int $v): Builder => $q->where('territory_id', $v))
            ->when($filters['country_id'] ?? null, fn (Builder $q, int $v): Builder => $q->where('country_id', $v))
            ->when($filters['locale'] ?? null, fn (Builder $q, string $v): Builder => $q->where('locale', $v))
            ->when($filters['object_type_id'] ?? null, fn (Builder $q, int $v): Builder => $q
                ->where('subject_type', Object_::class)
                ->whereIn('subject_id', Object_::query()->withUnmoderated()->where('object_type_id', $v)->select('id')));
    }

    /**
     * @param  Builder<StatDaily>  $query
     * @return array<string, int>
     */
    private function countsByKind(Builder $query): array
    {
        /** @var Collection<string, int> $totals */
        $totals = (clone $query)
            ->selectRaw('kind, sum(count) as total')
            ->groupBy('kind')
            ->pluck('total', 'kind');

        return collect(StatEventKind::cases())
            ->mapWithKeys(fn (StatEventKind $kind): array => [$kind->value => (int) ($totals[$kind->value] ?? 0)])
            ->all();
    }
}
