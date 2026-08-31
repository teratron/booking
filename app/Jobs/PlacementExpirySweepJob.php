<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Notification;
use App\Models\NotificationTemplate;
use App\Models\NotificationType;
use App\Models\Object_;
use App\Models\ObjectPlacement;
use App\Models\PlacementPackage;
use App\Models\User;
use App\Services\Audit\AuditJournal;
use App\Services\Placement\PlacementLifecycleService;
use App\Services\Settings\SettingsRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * The scheduled sweep behind the placement expiry flow — dispatched by the
 * scheduler (see routes/console.php), never reachable from a web route.
 * Two independent passes: warnings (a `placement_expiring` notification at
 * each configured offset, purely informational, never mutates a placement)
 * and the expiry action itself (applied once, on or after the expiry date,
 * raising a separate `package_expired` notification).
 *
 * Idempotency does not need a dedicated "processed" flag: warnings are
 * gated on "no notification of this type already exists for this object
 * today", and the expiry action's own effect — `PlacementLifecycleService::
 * grant()` replaces `object_placements` with a fresh window for the new
 * package — moves `ends_at` out of the past, so a re-run's query for
 * "expired and unprocessed" naturally stops matching an object already
 * handled. "hide" and "review" do not get that structural guarantee, so
 * both are gated on the same same-day notification check as warnings.
 */
final class PlacementExpirySweepJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** A daily lifecycle sweep — see config/horizon.php's own "default" supervisor. */
    public function __construct()
    {
        $this->onQueue('default');
    }

    public function handle(
        PlacementLifecycleService $lifecycle,
        SettingsRepository $settings,
        AuditJournal $journal,
    ): void {
        $this->raiseWarnings($settings);
        $this->applyExpiryActions($lifecycle, $settings, $journal);
    }

    private function raiseWarnings(SettingsRepository $settings): void
    {
        /** @var list<int> $offsets */
        $offsets = (array) $settings->get('placement.expiry_warning_offset_days');
        $graceDays = (int) $settings->get('placement.expiry_grace_days');

        // Pre-expiry and day-of offsets (e.g. 30, 14, 7, 3, 0) plus the
        // "after" point, which reuses the existing grace-period setting
        // rather than a second offset list.
        $daysBeforeList = [...$offsets, -$graceDays];

        foreach ($daysBeforeList as $daysBefore) {
            // "N days before" is a future ends_at (addDays); the "after"
            // entry carries a negative $daysBefore, so addDays(-N) is
            // subDays(N) — a past ends_at — automatically, with one
            // formula instead of two branches.
            $targetDate = now()->addDays((int) $daysBefore)->toDateString();
            $this->notifyEachPlacementEnding($targetDate, 'placement_expiring');
        }
    }

    private function applyExpiryActions(
        PlacementLifecycleService $lifecycle,
        SettingsRepository $settings,
        AuditJournal $journal,
    ): void {
        $action = (string) $settings->get('placement.expired_behaviour');

        ObjectPlacement::query()
            ->where('ends_at', '<', now()->toDateString())
            ->chunkById(200, function (Collection $placements) use ($action, $lifecycle, $journal): void {
                /** @var ObjectPlacement $placement */
                foreach ($placements as $placement) {
                    if ($this->alreadyNotifiedToday($placement->object_id, 'package_expired')) {
                        continue;
                    }

                    $object = Object_::query()->withoutGlobalScopes()->find($placement->object_id);

                    if (! $object instanceof Object_) {
                        continue;
                    }

                    $override = $placement->expiry_action_override ?? $action;

                    match ($override) {
                        'demote' => ($lowestTierPackage = $this->lowestTierPackageFor($object)) instanceof PlacementPackage
                            ? $lifecycle->grant($object, $lowestTierPackage)
                            : null,
                        // Inlined rather than routed through
                        // ObjectLifecycleService::hide(), which requires a
                        // non-null actor — this is a system-triggered write
                        // with none, the same posture
                        // AvailabilityAdministrationService::autoReset()
                        // already takes for its own scheduled writes.
                        'hide' => (function () use ($object, $journal): void {
                            $previous = $object->getOriginal('status');
                            $object->status = 'hidden';
                            $object->save();
                            $journal->record('object_hidden', $object, ['status' => $previous], ['status' => 'hidden'], null, ['object', 'placement']);
                        })(),
                        default => $journal->record(
                            event: 'placement_flagged_for_review',
                            target: $object,
                            newValues: ['reason' => 'placement_expired'],
                            tags: ['placement'],
                        ),
                    };

                    $this->createNotification($object, 'package_expired');
                }
            });
    }

    /**
     * The active package whose tier has the highest rank number — under
     * this seeder's ascending-best convention (rank 1 = VIP … rank 4 =
     * Standard), that is the worst/free tier, the correct demotion target.
     * Prefers a package scoped to the object's own category; falls back to
     * an any-category package when the category has none of its own.
     */
    private function lowestTierPackageFor(Object_ $object): ?PlacementPackage
    {
        $categoryScoped = PlacementPackage::query()
            ->join('placement_tiers', 'placement_tiers.id', '=', 'placement_packages.placement_tier_id')
            ->where('placement_packages.object_type_id', $object->object_type_id)
            ->where('placement_packages.is_active', true)
            ->orderByDesc('placement_tiers.rank')
            ->select('placement_packages.*')
            ->first();

        if ($categoryScoped instanceof PlacementPackage) {
            return $categoryScoped;
        }

        return PlacementPackage::query()
            ->join('placement_tiers', 'placement_tiers.id', '=', 'placement_packages.placement_tier_id')
            ->whereNull('placement_packages.object_type_id')
            ->where('placement_packages.is_active', true)
            ->orderByDesc('placement_tiers.rank')
            ->select('placement_packages.*')
            ->first();
    }

    private function notifyEachPlacementEnding(string $endsAtDate, string $typeKey): void
    {
        ObjectPlacement::query()
            ->whereDate('ends_at', $endsAtDate)
            ->chunkById(200, function (Collection $placements) use ($typeKey): void {
                /** @var ObjectPlacement $placement */
                foreach ($placements as $placement) {
                    if ($this->alreadyNotifiedToday($placement->object_id, $typeKey)) {
                        continue;
                    }

                    $object = Object_::query()->withoutGlobalScopes()->find($placement->object_id);

                    if ($object instanceof Object_) {
                        $this->createNotification($object, $typeKey);
                    }
                }
            });
    }

    private function alreadyNotifiedToday(int $objectId, string $typeKey): bool
    {
        return Notification::query()
            ->where('related_type', Object_::class)
            ->where('related_id', $objectId)
            ->whereHas('type', fn ($q) => $q->where('key', $typeKey))
            ->whereDate('created_at', today())
            ->exists();
    }

    private function createNotification(Object_ $object, string $typeKey): void
    {
        $type = NotificationType::query()->where('key', $typeKey)->first();

        if (! $type instanceof NotificationType || $object->owner_id === null) {
            return;
        }

        $recipient = User::query()->find($object->owner_id);

        if (! $recipient instanceof User) {
            return;
        }

        // No per-user language preference exists on the User model yet —
        // a real gap: "language follows the recipient" has nothing to
        // read from. Falls back to the portal's primary language rather
        // than inventing a new user column inside this job; the recipient
        // still gets a correctly-rendered message, just not necessarily in
        // their own language until that gap is closed.
        $locale = (string) (DB::table('languages')->where('is_primary', true)->value('code') ?? 'en');

        $template = $type->templates()
            ->where('locale', $locale)
            ->whereHas('channel', fn ($q) => $q->where('key', 'inbox'))
            ->first();

        $title = $typeKey;
        $body = '';

        if ($template instanceof NotificationTemplate) {
            $title = $template->subject ?? $typeKey;
            $body = $template->body;
        }

        Notification::query()->create([
            'recipient_id' => $recipient->id,
            'notification_type_id' => $type->id,
            'related_type' => Object_::class,
            'related_id' => $object->id,
            'title' => $title,
            'body' => $body,
            'locale' => $locale,
        ]);
    }
}
