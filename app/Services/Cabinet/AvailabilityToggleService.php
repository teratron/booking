<?php

declare(strict_types=1);

namespace App\Services\Cabinet;

use App\Models\Object_;
use App\Models\User;
use App\Services\Audit\AuditJournal;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The owner's own one-tap availability write path — deliberately separate
 * from the general object-edit flow. An owner flips a binary switch ("do I
 * have vacancies right now"); routing that through the object-edit path
 * would drag in form validation and moderation review, and a moderated
 * toggle would defeat the immediacy the switch exists for in the first
 * place. This method never calls the moderation pipeline — not a guard
 * against reaching it, simply the absence of a call, which is the whole
 * point of the exemption.
 *
 * `unspecified` toggles to `available`: an owner has no reason to tap the
 * switch in order to reach "not stated" on purpose, so the one sensible
 * target from that starting point is the same default a freshly onboarded
 * object already starts from.
 */
final class AvailabilityToggleService
{
    /** @var array<string, string> */
    private const array TOGGLE_TARGET = [
        'available' => 'unavailable',
        'unavailable' => 'available',
        'unspecified' => 'available',
    ];

    public function __construct(private readonly AuditJournal $journal) {}

    /**
     * Flips $object's availability to the other side of the switch, writing
     * the object's own columns and an append-only history row in a single
     * transaction, and resets the staleness clock — a tap is always a fresh
     * confirmation of the resulting value, changed or not.
     *
     * Never alters placement, catalog ordering, or contact-channel
     * visibility: this method touches only the `availability_*` columns on
     * $object and nothing else.
     */
    public function toggle(Object_ $object, User $actor): void
    {
        $previousStatus = $object->availability_status;
        $nextStatus = self::TOGGLE_TARGET[$previousStatus];

        DB::transaction(function () use ($object, $previousStatus, $nextStatus, $actor): void {
            $object->forceFill([
                'availability_status' => $nextStatus,
                'availability_changed_at' => now(),
                'availability_changed_by' => $actor->id,
                'availability_previous_status' => $previousStatus,
                'availability_last_confirmed_at' => now(),
                'availability_comment' => null,
            ])->save();

            $object->availabilityHistories()->create([
                'from_status' => $previousStatus,
                'to_status' => $nextStatus,
                'changed_at' => now(),
                'changed_by' => $actor->id,
                'source' => 'owner',
            ]);
        });

        $this->journal->record(
            'availability_toggled',
            $object,
            ['availability_status' => $previousStatus],
            ['availability_status' => $nextStatus],
            $actor,
            ['object', 'availability'],
        );

        $this->invalidateCaches($object);
    }

    /**
     * Tag-based invalidation over the same convention every other
     * object-touching write in this codebase already flushes under — one
     * shared key scheme, not a second one invented for the owner's side of
     * this feature.
     */
    private function invalidateCaches(Object_ $object): void
    {
        Cache::tags(['catalog', "territory:{$object->territory_id}", "object:{$object->id}"])->flush();
    }
}
