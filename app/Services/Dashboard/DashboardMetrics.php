<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Models\Object_;
use App\Models\User;
use App\Services\Authorization\ResourceQueryScoper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The counters the dashboard renders.
 *
 * Every count is scoped to the actor's grants for the same reason the lists
 * are: a country administrator who cannot open another country's object must
 * not learn from a headline figure how many of them exist. Scoping the list
 * and leaving the counter global would disclose exactly what the narrowing
 * was for.
 *
 * Counts are aggregates over the largest tables in the schema and the
 * dashboard is the panel's most-visited screen, so they are cached briefly
 * rather than computed per page load. The window is short on purpose: a
 * dashboard is a status display, and a stale status display is a wrong one.
 */
final class DashboardMetrics
{
    private const CACHE_SECONDS = 60;

    public function __construct(private readonly ResourceQueryScoper $scoper) {}

    /**
     * Object counts by publication state, plus the two operational figures an
     * administrator acts on: what is waiting for review, and what is about to
     * expire.
     *
     * @return array<string, int>
     */
    public function operational(User $actor): array
    {
        /** @var array<string, int> */
        return Cache::remember("dashboard.operational.{$actor->id}", self::CACHE_SECONDS, function () use ($actor): array {
            $states = ['draft', 'published', 'hidden', 'archived'];
            $counts = [];

            foreach ($states as $state) {
                $counts[$state] = $this->objects($actor)->where('status', $state)->count();
            }

            $counts['total'] = array_sum($counts);
            $counts['pending_moderation'] = $this->objects($actor)->where('moderation_status', 'pending')->count();
            $counts['reporting_vacancies'] = $this->objects($actor)
                ->where('availability_status', 'available')
                ->where('status', 'published')
                ->count();

            return $counts;
        });
    }

    /**
     * Registry sizes — owners, countries, and territories. Not scoped by the
     * object axes: a country administrator legitimately sees how many
     * countries the portal serves, since that is what their own scope is
     * expressed against.
     *
     * @return array<string, int>
     */
    public function registries(): array
    {
        /** @var array<string, int> */
        return Cache::remember('dashboard.registries', self::CACHE_SECONDS, static fn (): array => [
            'owners' => DB::table('objects')->distinct()->count('owner_id'),
            'countries' => DB::table('countries')->where('is_active', true)->count(),
            'territories' => DB::table('territories')->count(),
        ]);
    }

    /**
     * Figures that require the finance permission. Kept in their own method so
     * a caller without that grant never triggers the queries at all — an
     * unauthorized read that runs and is then discarded is still a read.
     *
     * @return array<string, int|string>
     */
    public function financial(User $actor): array
    {
        /** @var array<string, int|string> */
        return Cache::remember("dashboard.financial.{$actor->id}", self::CACHE_SECONDS, function () use ($actor): array {
            $expiringSoon = $this->objects($actor)
                ->join('object_placements', 'objects.id', '=', 'object_placements.object_id')
                ->whereBetween('object_placements.ends_at', [now(), now()->addDays(14)])
                ->count();

            return [
                'recorded_amount' => (string) DB::table('financial_records')
                    ->where('created_at', '>=', now()->subDays(30))
                    ->sum('amount'),
                'active_placements' => DB::table('object_placements')
                    ->where('starts_at', '<=', now())
                    ->where('ends_at', '>=', now())
                    ->count(),
                'expiring_placements' => $expiringSoon,
            ];
        });
    }

    /**
     * @return Builder<Object_>
     */
    private function objects(User $actor): Builder
    {
        return $this->scoper->narrow(
            Object_::query()->withUnmoderated(),
            $actor,
            'object.view',
            'country_id',
            'territory_id',
            'object_type_id',
        );
    }
}
