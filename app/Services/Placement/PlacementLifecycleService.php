<?php

declare(strict_types=1);

namespace App\Services\Placement;

use App\Models\Object_;
use App\Models\ObjectPlacement;
use App\Models\PlacementPackage;
use App\Models\User;
use App\Services\Audit\AuditJournal;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Grants, closes, and pins an object's placement. `grant()` is the only
 * writer of `object_placements` (the current row) and `placement_histories`
 * (the append-only record) together, in one transaction, so the two can
 * never drift apart — a package change updates "what is current" and
 * appends to "what happened" atomically or not at all.
 */
final class PlacementLifecycleService
{
    public function __construct(
        private readonly AuditJournal $journal,
        private readonly ?User $actor = null,
    ) {}

    /**
     * Grants $package to $object for its full validity period, closing any
     * prior placement first. The previous `object_placements` row is never
     * updated in place for the package/dates — it is replaced, and the
     * `placement_histories` row it leaves behind is what makes the change
     * append-only rather than overwritten.
     *
     * A grant may be free ($ledgerStatus's default) or carry a genuinely
     * recorded payment — the portal records that money changed hands, it
     * does not compute proration, refunds, or overlap against the prior
     * grant. $comment is the acting staff member's own note, stored beside
     * both the history row and the ledger entry it opens.
     */
    public function grant(
        Object_ $object,
        PlacementPackage $package,
        ?User $actor = null,
        ?string $comment = null,
        string $ledgerStatus = 'granted_free',
        ?float $amount = null,
    ): void {
        $actor ??= $this->actor;
        $startsAt = now()->toDateString();
        $endsAt = now()->addDays($package->validity_days)->toDateString();
        $amount ??= (float) $package->price;

        DB::transaction(function () use ($object, $package, $startsAt, $endsAt, $actor, $comment, $ledgerStatus, $amount): void {
            $previous = $object->placement;

            // Larastan types HasOne as non-nullable regardless of the
            // relation's real cardinality (a fresh object legitimately has
            // no `object_placements` row yet), so the prior row's fields
            // are extracted through an explicit instanceof check rather
            // than nullsafe access — same convention `T-2T04` established
            // for a genuinely-nullable BelongsTo.
            $previousPinnedPosition = null;
            $previousInternalPriority = 0;
            $previousCreatedAt = now();

            if ($previous instanceof ObjectPlacement) {
                $previousPinnedPosition = $previous->pinned_position;
                $previousInternalPriority = $previous->internal_priority;
                $previousCreatedAt = $previous->created_at;
            }

            DB::table('object_placements')->updateOrInsert(
                ['object_id' => $object->id],
                [
                    'placement_package_id' => $package->id,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'pinned_position' => $previousPinnedPosition,
                    'internal_priority' => $previousInternalPriority,
                    'expiry_action_override' => null,
                    'updated_at' => now(),
                    'created_at' => $previousCreatedAt,
                ],
            );

            // Close the prior grant's history row before opening a new
            // one — otherwise two rows sit open (ends_at null)
            // simultaneously, breaking "one open row = the current grant"
            // for every later reader (the expiry sweep's own query among
            // them).
            DB::table('placement_histories')
                ->where('object_id', $object->id)
                ->whereNull('ends_at')
                ->update(['ends_at' => $startsAt, 'updated_at' => now()]);

            DB::table('placement_histories')->insert([
                'object_id' => $object->id,
                'placement_package_id' => $package->id,
                'starts_at' => $startsAt,
                'ends_at' => null,
                'amount' => $amount,
                'currency' => $package->currency,
                'status' => $ledgerStatus,
                'granted_by' => $actor?->id,
                'comment' => $comment,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // The unified financial ledger reads this table, not
            // placement_histories — populating it here is what makes every
            // automated grant (a purchase, a renewal, a system demotion)
            // show up in the financial dashboard/report without an
            // administrator re-entering it by hand. A genuinely paid
            // transaction still gets its own richer row, entered directly
            // against this same table via the ledger resource, with real
            // payment_method/document_number/status detail this automatic
            // write does not have.
            DB::table('financial_records')->insert([
                'object_id' => $object->id,
                'service' => 'placement',
                'placement_package_id' => $package->id,
                'amount' => $amount,
                'currency' => $package->currency,
                'valid_from' => $startsAt,
                'valid_until' => $endsAt,
                'status' => $ledgerStatus,
                'comment' => $comment,
                'responsible_staff_id' => $actor?->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $this->journal->record(
            event: 'placement_granted',
            target: $object,
            oldValues: [],
            newValues: ['placement_package_id' => $package->id, 'starts_at' => $startsAt, 'ends_at' => $endsAt],
            actor: $actor,
            tags: ['placement'],
        );

        $this->invalidateCatalogCaches($object);
    }

    /**
     * Pins $object to $position within its own tier and scope. Per the
     * specification's own invariant, pinning never changes the tier a
     * package grants — this method touches `pinned_position` only.
     *
     * @throws InvalidArgumentException when $object holds no current placement
     */
    public function pin(Object_ $object, int $position, ?User $actor = null): void
    {
        $actor ??= $this->actor;
        $placement = $object->placement;

        if ($placement === null) {
            throw new InvalidArgumentException('Cannot pin an object with no current placement.');
        }

        $previousPosition = $placement->pinned_position;

        DB::table('object_placements')
            ->where('object_id', $object->id)
            ->update(['pinned_position' => $position, 'updated_at' => now()]);

        $this->journal->record(
            event: 'placement_pinned',
            target: $object,
            oldValues: ['pinned_position' => $previousPosition],
            newValues: ['pinned_position' => $position],
            actor: $actor,
            tags: ['placement'],
        );

        $this->invalidateCatalogCaches($object);
    }

    /**
     * Removes a manual pin, returning $object to ordinary tier-and-bump
     * ordering. A no-op when no pin exists — unpinning an unpinned object
     * is not an error, it is idempotent by design.
     */
    public function unpin(Object_ $object, ?User $actor = null): void
    {
        $actor ??= $this->actor;
        $placement = $object->placement;

        if ($placement === null || $placement->pinned_position === null) {
            return;
        }

        $previousPosition = $placement->pinned_position;

        DB::table('object_placements')
            ->where('object_id', $object->id)
            ->update(['pinned_position' => null, 'updated_at' => now()]);

        $this->journal->record(
            event: 'placement_unpinned',
            target: $object,
            oldValues: ['pinned_position' => $previousPosition],
            newValues: ['pinned_position' => null],
            actor: $actor,
            tags: ['placement'],
        );

        $this->invalidateCatalogCaches($object);
    }

    /**
     * Package changes, bumps, and pins each invalidate the catalog,
     * territory, and object-page caches for the affected object — the same
     * tag scheme `AvailabilityAdministrationService` established, reused
     * here rather than inventing a second one.
     */
    private function invalidateCatalogCaches(Object_ $object): void
    {
        Cache::tags(['catalog', "territory:{$object->territory_id}", "object:{$object->id}"])->flush();
    }
}
