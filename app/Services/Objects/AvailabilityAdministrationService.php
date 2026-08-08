<?php

declare(strict_types=1);

namespace App\Services\Objects;

use App\Exceptions\AvailabilityRevertRefusedException;
use App\Models\Object_;
use App\Models\User;
use App\Services\Audit\AuditJournal;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The administrator's own write path for an object's availability status —
 * override and revert, both narrow by design. Routing either through the
 * general object-edit path would drag in moderation, validation, and
 * full-object cache invalidation, none of which this operation wants: the
 * availability toggle is the one owner action that bypasses moderation
 * unconditionally, and the administrator's override honours the same
 * shape. Neither method here calls the moderation pipeline — that is not a
 * guard against a code path that could reach it, it is the simple absence
 * of a call, which is what "never enqueues a moderation request" means in
 * practice.
 */
final class AvailabilityAdministrationService
{
    private const array VALID_STATUSES = ['available', 'unavailable', 'unspecified'];

    public function __construct(private readonly AuditJournal $journal) {}

    /**
     * Sets $object's availability to $status, recording the transition and
     * resetting the staleness clock. `last_confirmed_at` updates on every
     * call, changed or not — an administrator setting the status to the
     * value it already held is still a fresh confirmation of it, which is
     * exactly the distinction `last_confirmed_at` exists to capture
     * separately from `changed_at`.
     */
    public function override(Object_ $object, string $status, User $actor, ?string $comment = null): void
    {
        if (! in_array($status, self::VALID_STATUSES, true)) {
            throw new InvalidArgumentException("Unknown availability status [{$status}].");
        }

        $this->applyStatus($object, $status, $actor, $comment, 'availability_overridden', 'administrator');
    }

    /**
     * Restores $object's availability to whatever it held immediately
     * before its current value, and — since this is a status change like
     * any other — appends a further history row rather than deleting the
     * one the override wrote.
     *
     * @throws AvailabilityRevertRefusedException when the object has never
     *                                            had a prior status recorded
     */
    public function revert(Object_ $object, User $actor): void
    {
        $previous = $object->availability_previous_status;

        if ($previous === null) {
            throw AvailabilityRevertRefusedException::noPriorStatus($object->id);
        }

        $this->applyStatus($object, $previous, $actor, null, 'availability_reverted', 'administrator');
    }

    /**
     * The scheduled sweep's own write — no administrator behind it, hence
     * no $actor parameter. Resets a stale `unavailable` back to `available`
     * with `source: automatic`, so a resulting badge is never mistaken for
     * an owner's own assertion. `last_confirmed_at` is deliberately left
     * untouched: the status is still genuinely unconfirmed by anyone, only
     * the public-facing value changed, and the owner reminder this sweep
     * raises still needs the stale clock to keep reading stale.
     */
    public function autoReset(Object_ $object): void
    {
        $this->applyStatus($object, 'available', null, null, 'availability_auto_reset', 'automatic', resetConfirmation: false);
    }

    private function applyStatus(
        Object_ $object,
        string $status,
        ?User $actor,
        ?string $comment,
        string $event,
        string $source,
        bool $resetConfirmation = true,
    ): void {
        $previousStatus = $object->availability_status;

        DB::transaction(function () use ($object, $status, $actor, $comment, $previousStatus, $source, $resetConfirmation): void {
            $object->forceFill([
                'availability_status' => $status,
                'availability_changed_at' => now(),
                'availability_changed_by' => $actor?->id,
                'availability_previous_status' => $previousStatus,
                'availability_last_confirmed_at' => $resetConfirmation ? now() : $object->availability_last_confirmed_at,
                'availability_comment' => $comment,
            ])->save();

            $object->availabilityHistories()->create([
                'from_status' => $previousStatus,
                'to_status' => $status,
                'changed_at' => now(),
                'changed_by' => $actor?->id,
                'source' => $source,
            ]);
        });

        $this->journal->record(
            $event,
            $object,
            ['availability_status' => $previousStatus],
            ['availability_status' => $status],
            $actor,
            ['object', 'availability'],
        );

        $this->invalidateCaches($object);
    }

    /**
     * Tag-based invalidation over Redis (this project's cache store), keyed
     * the way the catalog, territory, and object-page caches will need to
     * be written under once those surfaces exist — establishing the
     * convention now rather than inventing a second one later. Flushing a
     * tag nothing has written under yet is a correct no-op, not an error.
     */
    private function invalidateCaches(Object_ $object): void
    {
        Cache::tags(['catalog', "territory:{$object->territory_id}", "object:{$object->id}"])->flush();
    }
}
