<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Models\StatEvent;
use App\Services\Cabinet\ObjectStatisticsService;
use App\Support\Analytics\TrafficSourceChannel;
use Illuminate\Support\Collection;

/**
 * The portal-wide traffic-source breakdown `[TZ]` §23/§125 asks the back
 * office to surface — a visit count per {@see TrafficSourceChannel}, never a
 * per-visitor row. Read from the raw `stat_events` tier rather than
 * `stat_dailies`, the same choice {@see ObjectStatisticsService}
 * already made for the owner cabinet's own breakdown: the aggregate table's
 * grain deliberately excludes the source columns (a documented invariant of
 * the two-tier analytics model), so this dimension only exists for whatever
 * window the raw tier's retention still holds. Always grouped — a channel
 * count, never the underlying rows — so no caller of this service can ever
 * reconstruct a single visit.
 *
 * @phpstan-import-type AnalyticsFilters from AnalyticsReportingService
 */
final class TrafficSourceReportingService
{
    /**
     * One count per {@see TrafficSourceChannel}, zero-filled for a channel
     * with no recorded visit in the filtered window — the same
     * always-every-key shape {@see AnalyticsReportingService::summary()}
     * already returns for event kinds.
     *
     * @param  AnalyticsFilters  $filters
     * @return array<string, int>
     */
    public function byChannel(array $filters = []): array
    {
        /** @var Collection<string, int> $totals */
        $totals = StatEvent::query()
            ->whereNotNull('source_channel')
            ->when($filters['period_from'] ?? null, fn ($q, string $v) => $q->where('occurred_at', '>=', $v))
            ->when($filters['period_until'] ?? null, fn ($q, string $v) => $q->where('occurred_at', '<=', $v))
            ->when($filters['territory_id'] ?? null, fn ($q, int $v) => $q->where('territory_id', $v))
            ->when($filters['country_id'] ?? null, fn ($q, int $v) => $q->where('country_id', $v))
            ->when($filters['locale'] ?? null, fn ($q, string $v) => $q->where('locale', $v))
            ->selectRaw('source_channel, count(*) as total')
            ->groupBy('source_channel')
            ->pluck('total', 'source_channel');

        return collect(TrafficSourceChannel::cases())
            ->mapWithKeys(fn (TrafficSourceChannel $channel): array => [$channel->value => (int) ($totals[$channel->value] ?? 0)])
            ->all();
    }
}
