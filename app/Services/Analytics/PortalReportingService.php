<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Models\Banner;
use App\Models\BumpEvent;
use App\Models\ModerationRequest;
use App\Models\Object_;
use App\Models\Promotion;
use App\Models\StatDaily;
use App\Models\User;
use App\Services\Dashboard\DashboardMetrics;
use App\Services\Localization\LanguageRegistry;
use App\Support\Analytics\StatEventKind;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The eight derived figures `[TZ]` §89/§125 name beyond
 * {@see AnalyticsReportingService}'s own per-kind totals: most viewed
 * objects, most popular categories, and banner click-through rate read the
 * aggregate `stat_dailies` tier through that service's own filtered query,
 * so their dimensional scoping (territory, country, object type, locale)
 * never drifts from the rest of the statistics screen. The other five —
 * new owner, new object, bump, published promotion, and pending moderation
 * counts — are not event-derived at all; they read their own operational
 * tables directly, scoped by period only (each carries no `stat_dailies`
 * row to filter by territory or object type against). Pending moderation
 * is the one exception to "period-scoped": a moderation queue's depth is a
 * live gauge, not a historical figure, matching the same live-count
 * treatment {@see DashboardMetrics} already gives it.
 *
 * @phpstan-import-type AnalyticsFilters from AnalyticsReportingService
 */
final class PortalReportingService
{
    private const int DEFAULT_TOP_LIMIT = 10;

    public function __construct(
        private readonly AnalyticsReportingService $analytics,
        private readonly LanguageRegistry $languages,
    ) {}

    /**
     * @param  AnalyticsFilters  $filters
     * @return array{
     *     most_viewed_objects: list<array{objectId: int, name: string, views: int}>,
     *     most_popular_categories: list<array{objectTypeId: int, name: string, views: int}>,
     *     banner_click_through_rate: float,
     *     new_owner_count: int,
     *     new_object_count: int,
     *     bump_count: int,
     *     published_promotion_count: int,
     *     pending_moderation_count: int,
     * }
     */
    public function derivedFigures(array $filters, int $topLimit = self::DEFAULT_TOP_LIMIT): array
    {
        return [
            'most_viewed_objects' => $this->mostViewedObjects($filters, $topLimit),
            'most_popular_categories' => $this->mostPopularCategories($filters, $topLimit),
            'banner_click_through_rate' => $this->bannerClickThroughRate($filters),
            'new_owner_count' => $this->newOwnerCount($filters),
            'new_object_count' => $this->newObjectCount($filters),
            'bump_count' => $this->bumpCount($filters),
            'published_promotion_count' => $this->publishedPromotionCount($filters),
            'pending_moderation_count' => $this->pendingModerationCount(),
        ];
    }

    /**
     * @param  AnalyticsFilters  $filters
     * @return list<array{objectId: int, name: string, views: int}>
     */
    public function mostViewedObjects(array $filters, int $limit = self::DEFAULT_TOP_LIMIT): array
    {
        $rows = $this->analytics->scopedQuery($filters)
            ->where('subject_type', Object_::class)
            ->where('kind', StatEventKind::ObjectPageView->value)
            ->selectRaw('subject_id, sum(count) as views')
            ->groupBy('subject_id')
            ->orderByDesc('views')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $names = $this->objectNames(array_values($rows->pluck('subject_id')->map(fn (mixed $id): int => (int) $id)->all()));

        return array_values($rows->map(fn (StatDaily $row): array => [
            'objectId' => (int) $row->subject_id,
            'name' => $names[(int) $row->subject_id] ?? "#{$row->subject_id}",
            'views' => (int) $row->getAttribute('views'),
        ])->all());
    }

    /**
     * @param  AnalyticsFilters  $filters
     * @return list<array{objectTypeId: int, name: string, views: int}>
     */
    public function mostPopularCategories(array $filters, int $limit = self::DEFAULT_TOP_LIMIT): array
    {
        $rows = $this->analytics->scopedQuery($filters)
            ->where('subject_type', Object_::class)
            ->where('kind', StatEventKind::ObjectPageView->value)
            ->join('objects', 'objects.id', '=', 'stat_dailies.subject_id')
            ->selectRaw('objects.object_type_id, sum(stat_dailies.count) as views')
            ->groupBy('objects.object_type_id')
            ->orderByDesc('views')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $names = $this->objectTypeNames(array_values($rows->map(fn (StatDaily $row): int => (int) $row->getAttribute('object_type_id'))->all()));

        return array_values($rows->map(fn (StatDaily $row): array => [
            'objectTypeId' => (int) $row->getAttribute('object_type_id'),
            'name' => $names[(int) $row->getAttribute('object_type_id')] ?? '#'.$row->getAttribute('object_type_id'),
            'views' => (int) $row->getAttribute('views'),
        ])->all());
    }

    /**
     * Clicks over impressions for every banner `$filters` reaches, rounded
     * to four decimal places. Zero when no impression has been recorded yet
     * — a rate with an empty denominator is undefined, not zero, but a
     * report figure must still render something rather than divide by zero.
     *
     * @param  AnalyticsFilters  $filters
     */
    public function bannerClickThroughRate(array $filters): float
    {
        /** @var Collection<string, int> $totals */
        $totals = $this->analytics->scopedQuery($filters)
            ->where('subject_type', Banner::class)
            ->whereIn('kind', [StatEventKind::BannerImpression->value, StatEventKind::BannerClick->value])
            ->selectRaw('kind, sum(count) as total')
            ->groupBy('kind')
            ->pluck('total', 'kind');

        $impressions = (int) ($totals[StatEventKind::BannerImpression->value] ?? 0);
        $clicks = (int) ($totals[StatEventKind::BannerClick->value] ?? 0);

        return $impressions > 0 ? round($clicks / $impressions, 4) : 0.0;
    }

    /**
     * `whereHas('roles', ...)` rather than Spatie's own `role()` scope
     * deliberately: that scope resolves the named role eagerly and throws
     * `RoleDoesNotExist` when it has not been seeded, which turns a report
     * page into a 500 in any environment or test that has not run
     * `RoleSeeder` yet. A plain relationship filter degrades to zero
     * instead.
     *
     * @param  AnalyticsFilters  $filters
     */
    public function newOwnerCount(array $filters): int
    {
        return User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'object_owner'))
            ->when($filters['period_from'] ?? null, fn ($q, string $v) => $q->where('created_at', '>=', $v))
            ->when($filters['period_until'] ?? null, fn ($q, string $v) => $q->where('created_at', '<=', $v))
            ->count();
    }

    /** @param  AnalyticsFilters  $filters */
    public function newObjectCount(array $filters): int
    {
        return Object_::query()
            ->withUnmoderated()
            ->when($filters['period_from'] ?? null, fn ($q, string $v) => $q->where('created_at', '>=', $v))
            ->when($filters['period_until'] ?? null, fn ($q, string $v) => $q->where('created_at', '<=', $v))
            ->when($filters['territory_id'] ?? null, fn ($q, int $v) => $q->where('territory_id', $v))
            ->when($filters['country_id'] ?? null, fn ($q, int $v) => $q->where('country_id', $v))
            ->when($filters['object_type_id'] ?? null, fn ($q, int $v) => $q->where('object_type_id', $v))
            ->count();
    }

    /** @param  AnalyticsFilters  $filters */
    public function bumpCount(array $filters): int
    {
        return BumpEvent::query()
            ->when($filters['period_from'] ?? null, fn ($q, string $v) => $q->where('occurred_at', '>=', $v))
            ->when($filters['period_until'] ?? null, fn ($q, string $v) => $q->where('occurred_at', '<=', $v))
            ->when($filters['object_id'] ?? null, fn ($q, int $v) => $q->where('object_id', $v))
            ->count();
    }

    /** @param  AnalyticsFilters  $filters */
    public function publishedPromotionCount(array $filters): int
    {
        return Promotion::query()
            ->where('status', 'published')
            ->when($filters['period_from'] ?? null, fn ($q, string $v) => $q->where('starts_at', '>=', $v))
            ->when($filters['period_until'] ?? null, fn ($q, string $v) => $q->where('starts_at', '<=', $v))
            ->when($filters['territory_id'] ?? null, fn ($q, int $v) => $q->where('territory_id', $v))
            ->count();
    }

    /**
     * The current moderation queue depth — never period-scoped. Requests
     * decided outside `$filters`' own period are irrelevant to "what is
     * pending right now", the same reading `DashboardMetrics::operational()`
     * already gives this exact figure.
     */
    public function pendingModerationCount(): int
    {
        return ModerationRequest::query()->where('decision', 'pending')->count();
    }

    /** @param  list<int>  $objectIds
     * @return array<int, string> */
    private function objectNames(array $objectIds): array
    {
        if ($objectIds === []) {
            return [];
        }

        return DB::table('object_translations')
            ->whereIn('object_id', $objectIds)
            ->where('locale', $this->languages->primaryLocale())
            ->pluck('name', 'object_id')
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();
    }

    /** @param  list<int>  $objectTypeIds
     * @return array<int, string> */
    private function objectTypeNames(array $objectTypeIds): array
    {
        if ($objectTypeIds === []) {
            return [];
        }

        return DB::table('object_type_translations')
            ->whereIn('object_type_id', $objectTypeIds)
            ->where('locale', $this->languages->primaryLocale())
            ->pluck('name', 'object_type_id')
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();
    }
}
