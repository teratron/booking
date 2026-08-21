<?php

declare(strict_types=1);

namespace App\Services\Objects;

use App\Exceptions\BulkSelectionScopeException;
use App\Models\Language;
use App\Models\Notification;
use App\Models\Object_;
use App\Models\Territory;
use App\Models\User;
use App\Services\Audit\AuditJournal;
use App\Services\Authorization\ResourceQueryScoper;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * Runs one operation over a whole selection of objects, on behalf of either
 * the inline request path or the queued job — both call `execute()` with the
 * identical arguments, so a selection large enough to be queued behaves no
 * differently from one small enough to run inline.
 *
 * Every operation rejects the whole selection, never a subset, when any
 * record sits outside the actor's scope or policy grant: the check runs once,
 * over the whole loaded set, before any mutation begins. Every operation
 * journals one audit entry per affected record, mirroring the past-tense
 * event naming `ObjectLifecycleService` already uses for its own single-
 * object actions.
 */
final class ObjectBulkActionService
{
    public function __construct(
        private readonly AuditJournal $journal,
        private readonly ResourceQueryScoper $scoper,
        private readonly AvailabilityAdministrationService $availability,
    ) {}

    /**
     * @param  list<int>  $objectIds  plain IDs, not loaded models — this keeps a queued job's payload small and serializable
     * @param  array<string, mixed>  $parameters  operation-specific arguments; see the individual `*Operation()` methods below for the expected shape
     *
     * @throws BulkSelectionScopeException when any selected record sits outside the actor's scope or policy grant
     * @throws InvalidArgumentException when $operation is unknown or $parameters is missing a required key
     */
    public function execute(string $operation, array $objectIds, array $parameters, User $actor): void
    {
        // Validates the operation key itself before any query runs — an
        // unknown operation must fail before it spends a round trip.
        $permission = $this->permissionFor($operation, $parameters);

        // Bulk actions manage a selection irrespective of moderation status —
        // a staff member acting on a batch that includes an object still
        // awaiting its first moderation decision must not have that object
        // silently vanish from the selection, the same reasoning that lifts
        // the moderation scope for the staff panel's own list query.
        $query = Object_::query()->withUnmoderated()->whereIn('id', $objectIds);

        match ($operation) {
            'export' => $query->with(['objectType', 'country', 'territory', 'owner', 'translations']),
            'assign_manager' => $query->with('staff'),
            default => null,
        };

        $objects = $query->get();

        $this->assertWholeSelectionInScope($objects, count($objectIds), $actor, $permission);

        match ($operation) {
            'change_status' => $this->changeStatus($objects, $parameters, $actor),
            'archive' => $this->archive($objects, $actor),
            'assign_promotion_label' => $this->assignPromotionLabel($objects, $parameters, $actor),
            'move_territory' => $this->moveTerritory($objects, $parameters, $actor),
            'assign_manager' => $this->assignManager($objects, $parameters, $actor),
            'notify_owners' => $this->notifyOwners($objects, $parameters, $actor),
            'export' => $this->export($objects, $actor),
            'reset_stale_availability' => $this->resetStaleAvailability($objects, $actor),
            default => throw new InvalidArgumentException("Unknown bulk operation [{$operation}]."),
        };
    }

    /**
     * Validates the full selection in one pass, before any per-record loop
     * begins: a re-narrowed count mismatch means at least one selected
     * object sits outside the actor's scoped grant, and a policy denial on
     * any single record means at least one fails the record-level check
     * (the `Object_Policy` special cases a scope constraint alone cannot
     * express, such as `forceDelete`'s chief-administrator restriction).
     * Either failure refuses the whole batch — nothing is mutated first.
     *
     * @param  Collection<int, Object_>  $objects  every object actually found for the requested IDs
     */
    private function assertWholeSelectionInScope(Collection $objects, int $totalCount, User $actor, string $permission): void
    {
        $scopedCount = $this->scoper->narrow(
            Object_::query()->withUnmoderated()->whereIn('id', $objects->modelKeys()),
            $actor,
            $permission,
            'country_id',
            'territory_id',
            'object_type_id',
        )->count();

        if ($scopedCount !== $totalCount) {
            throw BulkSelectionScopeException::forSelection($totalCount - $scopedCount, $totalCount);
        }

        $policyAbility = $this->policyAbilityFor($permission);

        $deniedCount = $objects
            ->filter(static fn (Object_ $object): bool => Gate::forUser($actor)->denies($policyAbility, $object))
            ->count();

        if ($deniedCount > 0) {
            throw BulkSelectionScopeException::forSelection($deniedCount, $totalCount);
        }
    }

    /**
     * The permission key each operation checks scope against — the same
     * string `Object_Policy` passes to `ScopeAuthorizer::authorize()` for
     * the equivalent single-object action.
     *
     * @param  array<string, mixed>  $parameters
     */
    private function permissionFor(string $operation, array $parameters): string
    {
        return match ($operation) {
            'change_status' => ($parameters['status'] ?? null) === 'published' ? 'object.publish' : 'object.edit',
            'archive' => 'object.delete',
            'assign_promotion_label', 'move_territory', 'assign_manager', 'notify_owners', 'reset_stale_availability' => 'object.edit',
            'export' => 'object.export',
            default => throw new InvalidArgumentException("Unknown bulk operation [{$operation}]."),
        };
    }

    /**
     * `Object_Policy`'s methods are named after the action they guard
     * (`update`, `publish`, `delete`, `export`), not after the permission
     * string that names the underlying grant — this maps one to the other
     * so the same permission key drives both the query-scope narrowing and
     * the record-level Gate check.
     */
    private function policyAbilityFor(string $permission): string
    {
        return match ($permission) {
            'object.publish' => 'publish',
            'object.delete' => 'delete',
            'object.export' => 'export',
            default => 'update',
        };
    }

    /**
     * Shared status transition backing the publish / hide / set-to-draft
     * bulk operations — three thin callers of one status-changing method,
     * mirroring `ObjectLifecycleService::publish()` / `hide()` /
     * `saveAsDraft()` for the single-object case.
     *
     * Parameters: `status` => `published` | `hidden` | `draft`.
     *
     * @param  Collection<int, Object_>  $objects
     * @param  array<string, mixed>  $parameters
     */
    private function changeStatus(Collection $objects, array $parameters, User $actor): void
    {
        $status = $parameters['status'] ?? null;

        if (! in_array($status, ['published', 'hidden', 'draft'], true)) {
            throw new InvalidArgumentException('Changing status in bulk requires status to be one of: published, hidden, draft.');
        }

        $event = match ($status) {
            'published' => 'object_bulk_published',
            'hidden' => 'object_bulk_hidden',
            default => 'object_bulk_status_changed',
        };

        DB::transaction(function () use ($objects, $status, $event, $actor): void {
            foreach ($objects as $object) {
                $previous = $object->status;
                $object->status = $status;
                $object->save();

                $this->journal->record($event, $object, ['status' => $previous], ['status' => $status], $actor, ['object', 'bulk']);
            }
        });
    }

    /**
     * Soft-deletes every selected object — the same mechanism as
     * `ObjectLifecycleService::archive()`, deliberately not a
     * `status = 'archived'` write, so a bulk archive and a single-object
     * archive leave the record in an identical state.
     *
     * @param  Collection<int, Object_>  $objects
     */
    private function archive(Collection $objects, User $actor): void
    {
        DB::transaction(function () use ($objects, $actor): void {
            foreach ($objects as $object) {
                $previousStatus = $object->status;
                $object->delete();

                $this->journal->record('object_bulk_archived', $object, ['status' => $previousStatus], [], $actor, ['object', 'bulk']);
            }
        });
    }

    /**
     * Grants a promotional label to every selected object, replacing any
     * grant whose window overlaps the new one rather than stacking
     * duplicates. Border colour is a property of the label an administrator
     * picks, not an independent per-object field, so assigning the label
     * *is* the whole mutation.
     *
     * Overlap is measured against the *incoming* window, not against today.
     * Clearing only what is active today leaves a future-dated grant in
     * place, so scheduling two overlapping future windows for the same
     * object stacks both — and once that window opens, the catalog's
     * ordering join matches an object twice and lists it twice. The
     * invariant the rest of the system reads ("the one label an object
     * currently carries") has to hold for every future day, not just for
     * the day the grant was made.
     *
     * Parameters: `promotion_label_id` (int), `starts_at` (date string),
     * `ends_at` (date string).
     *
     * @param  Collection<int, Object_>  $objects
     * @param  array<string, mixed>  $parameters
     */
    private function assignPromotionLabel(Collection $objects, array $parameters, User $actor): void
    {
        $promotionLabelId = (int) ($parameters['promotion_label_id'] ?? 0);
        $startsAt = $parameters['starts_at'] ?? null;
        $endsAt = $parameters['ends_at'] ?? null;

        if ($promotionLabelId <= 0 || ! is_string($startsAt) || $startsAt === '' || ! is_string($endsAt) || $endsAt === '') {
            throw new InvalidArgumentException('Assigning a promotional label in bulk requires promotion_label_id, starts_at, and ends_at.');
        }

        DB::transaction(function () use ($objects, $promotionLabelId, $startsAt, $endsAt, $actor): void {
            foreach ($objects as $object) {
                // Two date ranges overlap exactly when each starts on or
                // before the other ends — the standard interval test, and
                // the reason neither bound is compared against today.
                DB::table('object_promotions')
                    ->where('object_id', $object->id)
                    ->where('starts_at', '<=', $endsAt)
                    ->where('ends_at', '>=', $startsAt)
                    ->delete();

                DB::table('object_promotions')->insert([
                    'object_id' => $object->id,
                    'promotion_label_id' => $promotionLabelId,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'granted_by' => $actor->id,
                    'weight' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $this->journal->record('object_bulk_promotion_assigned', $object, [], [
                    'promotion_label_id' => $promotionLabelId,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                ], $actor, ['object', 'bulk']);
            }
        });
    }

    /**
     * Moves every selected object to another territory, recomputing
     * `country_id` from the target territory's own `country_id` — objects
     * denormalize country the same way territories denormalize it onto
     * their own descendants, so a territory move must keep both columns
     * consistent rather than leaving the old country stamped on the row.
     *
     * Parameters: `territory_id` (int).
     *
     * @param  Collection<int, Object_>  $objects
     * @param  array<string, mixed>  $parameters
     */
    private function moveTerritory(Collection $objects, array $parameters, User $actor): void
    {
        $territoryId = (int) ($parameters['territory_id'] ?? 0);

        if ($territoryId <= 0) {
            throw new InvalidArgumentException('Moving objects to another territory in bulk requires a territory_id.');
        }

        $territory = Territory::query()->findOrFail($territoryId);

        DB::transaction(function () use ($objects, $territory, $actor): void {
            foreach ($objects as $object) {
                $previous = ['territory_id' => $object->territory_id, 'country_id' => $object->country_id];

                // Both columns are unsigned in the schema; the source IDs are
                // always non-negative auto-increment keys, so max(0, ...) only
                // narrows the static type, it never changes the runtime value.
                $object->territory_id = max(0, $territory->id);
                $object->country_id = max(0, $territory->country_id);
                $object->save();

                $this->journal->record('object_bulk_moved_territory', $object, $previous, [
                    'territory_id' => $territory->id,
                    'country_id' => $territory->country_id,
                ], $actor, ['object', 'bulk']);
            }
        });
    }

    /**
     * Attaches a staff member to every selected object via the
     * `object_user` pivot, without detaching whoever is already attached —
     * a bulk manager assignment adds a grant, it never revokes one another
     * account was given.
     *
     * Parameters: `manager_id` (int).
     *
     * @param  Collection<int, Object_>  $objects
     * @param  array<string, mixed>  $parameters
     */
    private function assignManager(Collection $objects, array $parameters, User $actor): void
    {
        $managerId = (int) ($parameters['manager_id'] ?? 0);

        if ($managerId <= 0) {
            throw new InvalidArgumentException('Assigning a manager in bulk requires a manager_id.');
        }

        DB::transaction(function () use ($objects, $managerId, $actor): void {
            foreach ($objects as $object) {
                $object->staff()->syncWithoutDetaching([$managerId]);

                $this->journal->record('object_bulk_manager_assigned', $object, [], ['manager_id' => $managerId], $actor, ['object', 'bulk']);
            }
        });
    }

    /**
     * Creates one notification per selected object, addressed to that
     * object's owner. Only the notification record is created here — no
     * channel delivery, no template resolution; that is a separate, later
     * concern layered on top of this table.
     *
     * User accounts carry no locale column of their own, so a recipient's
     * own locale cannot be resolved without a per-row query that would
     * exist purely to fail; the primary language's code is used for every
     * recipient, which is exactly the documented fallback for when a
     * recipient locale is unavailable.
     *
     * Parameters: `title` (string), `body` (string).
     *
     * @param  Collection<int, Object_>  $objects
     * @param  array<string, mixed>  $parameters
     */
    private function notifyOwners(Collection $objects, array $parameters, User $actor): void
    {
        $title = $parameters['title'] ?? null;
        $body = $parameters['body'] ?? null;

        if (! is_string($title) || $title === '' || ! is_string($body) || $body === '') {
            throw new InvalidArgumentException('Notifying owners in bulk requires a title and a body.');
        }

        $notificationTypeId = DB::table('notification_types')->where('key', 'administration_message')->value('id');

        if ($notificationTypeId === null) {
            throw new RuntimeException('Notification type [administration_message] is not registered.');
        }

        $locale = Language::query()->where('is_primary', true)->value('code');

        DB::transaction(function () use ($objects, $title, $body, $notificationTypeId, $locale, $actor): void {
            foreach ($objects as $object) {
                // An ownerless object has no one to notify — recipient_id on
                // the notifications table is not nullable, so this is a
                // silent skip, not a failure of the whole batch.
                if ($object->owner_id === null) {
                    continue;
                }

                Notification::query()->create([
                    'recipient_id' => $object->owner_id,
                    'notification_type_id' => $notificationTypeId,
                    'related_type' => Object_::class,
                    'related_id' => $object->id,
                    'title' => $title,
                    'body' => $body,
                    'locale' => $locale,
                    'created_by' => $actor->id,
                ]);

                $this->journal->record('object_bulk_owners_notified', $object, [], ['recipient_id' => $object->owner_id], $actor, ['object', 'bulk']);
            }
        });
    }

    /**
     * Resets every selected object's availability to `available`, one at a
     * time through {@see AvailabilityAdministrationService::override()} —
     * that method already owns the transaction, the paired history row, the
     * journal entry, and the cache invalidation for a single object, so
     * this loop adds nothing beyond calling it once per selected record.
     * Trusts the selection as given: the ordinary flow is filtering to the
     * "status not updated recently" quick filter first, so this does not
     * re-derive staleness itself.
     *
     * @param  Collection<int, Object_>  $objects
     */
    private function resetStaleAvailability(Collection $objects, User $actor): void
    {
        foreach ($objects as $object) {
            $this->availability->override($object, 'available', $actor);
        }
    }

    /**
     * Writes a CSV of the selection to the default filesystem disk under an
     * `exports/` directory, then journals completion against every included
     * object, naming the stored path. Always queued by the caller,
     * regardless of selection size — this is a plain export write, not the
     * column-mapping / dedup / multi-format pipeline scoped separately
     * elsewhere.
     *
     * @param  Collection<int, Object_>  $objects
     */
    private function export(Collection $objects, User $actor): void
    {
        $rows = [['id', 'name', 'type_key', 'country_code', 'territory_name', 'owner_name', 'status']];

        foreach ($objects as $object) {
            // Explicit null checks, not the nullsafe operator: the object
            // type, country, and owner relations are FK-guaranteed non-null
            // in practice, but the export must not fatal on a row where an
            // eager-loaded relation genuinely failed to resolve.
            $objectType = $object->objectType;
            $country = $object->country;
            $owner = $object->owner;

            $rows[] = [
                (string) $object->id,
                (string) $object->name,
                $objectType === null ? '' : $objectType->key,
                $country === null ? '' : $country->code,
                (string) $object->territory?->name,
                $owner === null ? '' : $owner->name,
                $object->status,
            ];
        }

        $path = 'exports/objects-'.now()->format('Ymd-His').'-'.Str::random(8).'.csv';

        Storage::disk('local')->put($path, $this->toCsv($rows));

        DB::transaction(function () use ($objects, $path, $actor): void {
            foreach ($objects as $object) {
                $this->journal->record('object_bulk_exported', $object, [], ['path' => $path], $actor, ['object', 'bulk']);
            }
        });
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function toCsv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+b');

        if ($handle === false) {
            throw new RuntimeException('Unable to open a temporary stream for the export CSV.');
        }

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv === false ? '' : $csv;
    }
}
