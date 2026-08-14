<?php

declare(strict_types=1);

namespace App\Services\Cabinet;

use App\Models\Object_;
use App\Models\StatEvent;
use App\Services\Analytics\AnalyticsReportingService;
use App\Support\Analytics\StatEventKind;
use App\Support\Analytics\TrafficSourceChannel;
use App\Support\Cabinet\ObjectChannelClickCount;
use App\Support\Cabinet\ObjectStatisticsSummary;
use App\Support\Cabinet\ObjectTrafficSourceCount;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Assembles the owner cabinet's dedicated statistics page read model for one
 * object — all-time page views, photo views, the per-channel contact-click
 * breakdown, the traffic-source breakdown, and the favorite count.
 *
 * The kind-level totals (page views, photo views, contact clicks) come from
 * {@see AnalyticsReportingService::forOwner()}, scoped to the object's own
 * owner — the same owner-intersection guarantee that service already
 * provides for every other caller. The per-channel and traffic-source
 * breakdowns need dimensions that flat method does not expose, so they read
 * `stat_dailies` and `stat_events` directly, scoped to the same object.
 *
 * Traffic sources are read from the raw `stat_events` tier rather than the
 * `stat_dailies` rollup: the aggregate table's grain deliberately excludes
 * the source columns (a fixed rollup grain is a documented invariant of the
 * two-tier analytics model), so the source dimension only exists for
 * whatever window the raw tier's own retention policy still holds. This is
 * an honest reading of the retained window, not a synthesized "all-time"
 * figure the schema does not actually carry.
 */
final class ObjectStatisticsService
{
    public function __construct(
        private readonly AnalyticsReportingService $reporting,
    ) {}

    /**
     * Never throws for an object with no recorded activity yet — every
     * figure here degrades to zero or an empty breakdown, which is a
     * legitimate state for a freshly onboarded object.
     */
    public function summarize(Object_ $object): ObjectStatisticsSummary
    {
        $counts = $this->reporting->forOwner((int) $object->owner_id, ['object_id' => $object->id]);

        return new ObjectStatisticsSummary(
            objectName: (string) ($object->name ?? ''),
            pageViews: $counts[StatEventKind::ObjectPageView->value] ?? 0,
            photoViews: $counts[StatEventKind::PhotoView->value] ?? 0,
            contactClicksTotal: $counts[StatEventKind::ContactClick->value] ?? 0,
            channelClicks: $this->channelClicks($object),
            trafficSources: $this->trafficSources($object),
            favoriteCount: $this->favoriteCount($object),
        );
    }

    /**
     * @return list<ObjectChannelClickCount>
     */
    private function channelClicks(Object_ $object): array
    {
        $locale = app()->getLocale();

        /** @var Collection<int, object{channel_key: string, channel_label: ?string, total: int|string}> $rows */
        $rows = DB::table('stat_dailies')
            ->join('contact_channel_types', 'contact_channel_types.id', '=', 'stat_dailies.contact_channel_type_id')
            ->leftJoin('contact_channel_type_translations', function (JoinClause $join) use ($locale): void {
                $join->on('contact_channel_type_translations.contact_channel_type_id', '=', 'contact_channel_types.id')
                    ->where('contact_channel_type_translations.locale', '=', $locale);
            })
            ->where('stat_dailies.subject_type', Object_::class)
            ->where('stat_dailies.subject_id', $object->id)
            ->where('stat_dailies.kind', StatEventKind::ContactClick->value)
            ->groupBy('contact_channel_types.id', 'contact_channel_types.key', 'contact_channel_type_translations.display_name')
            ->orderByDesc('total')
            ->selectRaw('contact_channel_types.key as channel_key, contact_channel_type_translations.display_name as channel_label, sum(stat_dailies.count) as total')
            ->get();

        return array_values($rows
            ->map(fn (object $row): ObjectChannelClickCount => new ObjectChannelClickCount(
                channelKey: $row->channel_key,
                label: $row->channel_label ?? Str::headline($row->channel_key),
                count: (int) $row->total,
            ))
            ->all());
    }

    /**
     * @return list<ObjectTrafficSourceCount>
     */
    private function trafficSources(Object_ $object): array
    {
        /** @var Collection<int, object{source_channel: string, source_domain: ?string, source_campaign: ?string, total: int|string}> $rows */
        $rows = StatEvent::query()
            ->where('subject_type', Object_::class)
            ->where('subject_id', $object->id)
            ->whereNotNull('source_channel')
            ->groupBy('source_channel', 'source_domain', 'source_campaign')
            ->orderByDesc('total')
            ->selectRaw('source_channel, source_domain, source_campaign, count(*) as total')
            ->get();

        return array_values($rows
            ->map(fn (object $row): ObjectTrafficSourceCount => new ObjectTrafficSourceCount(
                channel: TrafficSourceChannel::from($row->source_channel),
                domain: $row->source_domain,
                campaign: $row->source_campaign,
                count: (int) $row->total,
            ))
            ->all());
    }

    /**
     * Reads the existing `favorites` table directly — a visitor-facing
     * demand signal, not something this page's own instrumentation owns.
     * Legitimately zero until the visitor-facing favorite toggle writes to
     * it; that absence is not this page's concern to fix.
     */
    private function favoriteCount(Object_ $object): int
    {
        return (int) DB::table('favorites')->where('object_id', $object->id)->count();
    }
}
