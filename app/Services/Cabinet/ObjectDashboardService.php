<?php

declare(strict_types=1);

namespace App\Services\Cabinet;

use App\Models\Object_;
use App\Models\ObjectPlacement;
use App\Models\StatDaily;
use App\Services\Settings\SettingsRepository;
use App\Support\Analytics\StatEventKind;
use App\Support\Cabinet\ObjectDashboardSummary;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Assembles the owner cabinet dashboard's read model for one object — the
 * current placement grant, view and contact-click counts, and whether the
 * placement is close enough to expiry to warrant surfacing that fact
 * outside the notification inbox.
 *
 * Every figure here reads an existing aggregate or registry table; nothing
 * is computed by re-deriving state another service already owns (placement
 * grants, availability, view/click totals).
 */
final class ObjectDashboardService
{
    /**
     * The channel-registry keys this portal groups under one "messenger
     * clicks" figure. The registry has no single "messenger" channel of its
     * own — the dashboard figure is the sum of every instant-messenger
     * channel a contact click can be attributed to.
     *
     * @var list<string>
     */
    private const MESSENGER_CHANNEL_KEYS = ['whatsapp', 'viber', 'telegram'];

    /** @var list<string> */
    private const WEBSITE_CHANNEL_KEYS = ['website'];

    public function __construct(
        private readonly SettingsRepository $settings,
    ) {}

    /**
     * Builds the dashboard summary for $object.
     *
     * Never throws for a missing placement, missing statistics, or missing
     * contact-channel registry rows — every one of those is a legitimate
     * state for a freshly onboarded object, not an error condition.
     */
    public function summarize(Object_ $object): ObjectDashboardSummary
    {
        $placement = ObjectPlacement::query()
            ->with('package.tier')
            ->where('object_id', $object->id)
            ->first();

        $package = $placement?->package;
        $tier = $package?->tier;

        [$warningActive, $daysRemaining] = $this->resolveExpiryWarning($placement);

        return new ObjectDashboardSummary(
            objectName: (string) ($object->name ?? ''),
            packageName: $package?->name,
            tierLabel: $tier?->label,
            placementExpiresAt: $placement?->ends_at,
            expiryWarningActive: $warningActive,
            expiryWarningDaysRemaining: $daysRemaining,
            catalogPosition: null,
            viewsToday: $this->pageViewCount($object, Carbon::now()->startOfDay()),
            viewsThisWeek: $this->pageViewCount($object, Carbon::now()->subDays(6)->startOfDay()),
            viewsThisMonth: $this->pageViewCount($object, Carbon::now()->subDays(29)->startOfDay()),
            viewsAllTime: $this->pageViewCount($object, null),
            messengerClicks: $this->contactClickCount($object, self::MESSENGER_CHANNEL_KEYS),
            websiteClicks: $this->contactClickCount($object, self::WEBSITE_CHANNEL_KEYS),
            availabilityStatus: (string) $object->availability_status,
        );
    }

    /**
     * Whether $placement's own end date falls inside the administrator-
     * configured warning window, and how many days remain if so.
     *
     * The window's width is the widest of the configured pre-expiry warning
     * offsets (e.g. 30/14/7/3/0 days before) — the same schedule the
     * scheduled expiry sweep uses to raise its own notifications, read here
     * only to decide whether this dashboard should say something too, never
     * to duplicate that sweep's own writes.
     *
     * @return array{0: bool, 1: ?int}
     */
    private function resolveExpiryWarning(?ObjectPlacement $placement): array
    {
        if (! $placement instanceof ObjectPlacement) {
            return [false, null];
        }

        $today = Carbon::now()->startOfDay();
        $expiresOn = $placement->ends_at->copy()->startOfDay();

        if ($expiresOn->lessThan($today)) {
            // Already past its own end date — the scheduled expiry sweep
            // owns that state (demotion, hiding, or review-flagging); this
            // dashboard only warns about an upcoming expiry, not one that
            // has already lapsed.
            return [false, null];
        }

        $daysRemaining = (int) $today->diffInDays($expiresOn);

        /** @var list<int> $offsets */
        $offsets = array_map(static fn (mixed $value): int => (int) $value, (array) $this->settings->get('placement.expiry_warning_offset_days'));
        $warningWindowDays = $offsets === [] ? 0 : max($offsets);

        return [$daysRemaining <= $warningWindowDays, $daysRemaining];
    }

    private function pageViewCount(Object_ $object, ?Carbon $since): int
    {
        $query = StatDaily::query()
            ->where('subject_type', Object_::class)
            ->where('subject_id', $object->id)
            ->where('kind', StatEventKind::ObjectPageView->value);

        if ($since instanceof Carbon) {
            $query->where('date', '>=', $since->toDateString());
        }

        return (int) $query->sum('count');
    }

    /**
     * @param  list<string>  $channelKeys
     */
    private function contactClickCount(Object_ $object, array $channelKeys): int
    {
        $channelIds = DB::table('contact_channel_types')->whereIn('key', $channelKeys)->pluck('id');

        if ($channelIds->isEmpty()) {
            return 0;
        }

        return (int) StatDaily::query()
            ->where('subject_type', Object_::class)
            ->where('subject_id', $object->id)
            ->where('kind', StatEventKind::ContactClick->value)
            ->whereIn('contact_channel_type_id', $channelIds)
            ->sum('count');
    }
}
