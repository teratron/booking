<?php

declare(strict_types=1);

namespace App\Services\Placement;

use App\Exceptions\BumpRefusedException;
use App\Models\BumpEvent;
use App\Models\Object_;
use App\Models\ObjectPlacement;
use App\Models\ObjectType;
use App\Models\PlacementPackage;
use App\Models\Territory;
use App\Models\User;
use App\Services\Audit\AuditJournal;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Moves an object to the first free position within its own tier, for one
 * scope — never above the tier, and never affecting a sibling scope. Every
 * one of the specification's five bump types (free, paid, automatic, owner,
 * administrator) is accepted as a matter of contract even though only
 * administrator-initiated has a caller in this phase; owner-initiated
 * arrives with the cabinet in a later phase and must not force a signature
 * change when it does.
 */
final class BumpService
{
    public function __construct(
        private readonly AuditJournal $journal,
        private readonly PlacementOrderingService $ordering,
    ) {}

    /**
     * @throws BumpRefusedException when the object holds no current placement, the
     *                              package forbids bumping, or — for a `free` bump — the interval or
     *                              allowance limit has not been satisfied
     */
    public function bump(
        Object_ $object,
        Territory|ObjectType $scope,
        User $actor,
        string $type = 'administrator',
        ?string $comment = null,
    ): BumpEvent {
        $placement = $object->placement;

        if (! $placement instanceof ObjectPlacement) {
            throw BumpRefusedException::noCurrentPlacement($object->id);
        }

        $package = $placement->package;

        if (! $package instanceof PlacementPackage || ! $package->bump_allowed) {
            throw BumpRefusedException::notAllowedByPackage($placement->placement_package_id);
        }

        if ($type === 'free') {
            $this->assertFreeBumpEligible($object, $placement, $package, $scope);
        }

        $previousPosition = $this->positionWithinTier($object, $package, $scope);
        $price = $type === 'paid' ? $package->paid_bump_price : null;

        $event = DB::transaction(function () use ($object, $package, $scope, $actor, $type, $price, $comment, $previousPosition): BumpEvent {
            /** @var BumpEvent $event */
            $event = BumpEvent::query()->create([
                'object_id' => $object->id,
                'placement_package_id' => $package->id,
                'scope_type' => $scope::class,
                'scope_id' => $scope->id,
                'occurred_at' => now(),
                'type' => $type,
                'actor_id' => $actor->id,
                'previous_position' => $previousPosition,
                'new_position' => 1,
                'price' => $price,
                'comment' => $comment,
            ]);

            return $event;
        });

        $this->journal->record(
            event: 'object_bumped',
            target: $object,
            oldValues: ['position' => $previousPosition],
            newValues: ['position' => 1, 'type' => $type, 'scope_type' => $scope::class, 'scope_id' => $scope->id],
            actor: $actor,
            tags: ['placement', 'bump'],
        );

        Cache::tags(['catalog', "territory:{$object->territory_id}", "object:{$object->id}"])->flush();

        return $event;
    }

    /**
     * @throws BumpRefusedException
     */
    private function assertFreeBumpEligible(Object_ $object, ObjectPlacement $placement, PlacementPackage $package, Territory|ObjectType $scope): void
    {
        if ($package->bump_interval_hours !== null) {
            $lastFreeBump = BumpEvent::query()
                ->where('object_id', $object->id)
                ->where('scope_type', $scope::class)
                ->where('scope_id', $scope->id)
                ->where('type', 'free')
                ->orderByDesc('occurred_at')
                ->first();

            if ($lastFreeBump instanceof BumpEvent
                && $lastFreeBump->occurred_at->copy()->addHours($package->bump_interval_hours)->isFuture()) {
                throw BumpRefusedException::intervalNotElapsed($object->id, $package->bump_interval_hours);
            }
        }

        if ($package->free_bumps_per_period !== null) {
            // The period is the object's current placement term, not a
            // rolling window — a new grant resets the allowance, since the
            // bump-duration limit is one of the package's own
            // administrator-set terms.
            $usedThisPeriod = BumpEvent::query()
                ->where('object_id', $object->id)
                ->where('scope_type', $scope::class)
                ->where('scope_id', $scope->id)
                ->where('type', 'free')
                ->where('occurred_at', '>=', $placement->starts_at)
                ->count();

            if ($usedThisPeriod >= $package->free_bumps_per_period) {
                throw BumpRefusedException::allowanceExhausted($object->id, $package->free_bumps_per_period);
            }
        }
    }

    /**
     * The object's 1-based position among objects sharing its tier, ordered
     * for $scope — recorded on the event for audit/reporting purposes only.
     * Live ordering always re-reads `PlacementOrderingService` directly;
     * this is never consulted to compute a future position.
     */
    private function positionWithinTier(Object_ $object, PlacementPackage $package, Territory|ObjectType $scope): ?int
    {
        $tierId = $package->placement_tier_id;

        $query = Object_::query()
            ->whereHas('placement', function ($placementQuery) use ($tierId): void {
                $placementQuery->whereHas('package', function ($packageQuery) use ($tierId): void {
                    $packageQuery->where('placement_tier_id', $tierId);
                });
            });

        $ordered = $this->ordering->apply($query, $scope)->pluck('id')->all();
        $index = array_search($object->id, $ordered, true);

        return $index === false ? null : (int) $index + 1;
    }
}
