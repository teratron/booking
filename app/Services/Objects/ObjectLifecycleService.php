<?php

declare(strict_types=1);

namespace App\Services\Objects;

use App\Models\ModerationRequest;
use App\Models\Object_;
use App\Models\ObjectTranslation;
use App\Models\User;
use App\Services\Audit\AuditJournal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The object form's lifecycle actions — everything beyond an ordinary field
 * save.
 *
 * An administrator's own edit through this form publishes directly; the
 * moderation queue exists for owner-submitted changes, which arrive through
 * the cabinet (a later phase), and conflating the two would put staff work in
 * a queue staff themselves clear. Every method here therefore mutates the
 * object immediately and journals what it did — none of them route through
 * the moderation pipeline, with the single exception of `returnForRevision`,
 * whose entire purpose is to create the record the owner will see.
 */
final class ObjectLifecycleService
{
    public function __construct(private readonly AuditJournal $journal) {}

    public function saveAsDraft(Object_ $object, User $actor): void
    {
        $previous = $object->getOriginal('status');
        $object->status = 'draft';
        $object->save();

        $this->journal->record('object_saved_as_draft', $object, ['status' => $previous], ['status' => 'draft'], $actor, ['object']);
    }

    public function publish(Object_ $object, User $actor): void
    {
        $previous = $object->getOriginal('status');
        $object->status = 'published';
        $object->save();

        $this->journal->record('object_published', $object, ['status' => $previous], ['status' => 'published'], $actor, ['object']);
    }

    public function hide(Object_ $object, User $actor): void
    {
        $previous = $object->getOriginal('status');
        $object->status = 'hidden';
        $object->save();

        $this->journal->record('object_hidden', $object, ['status' => $previous], ['status' => 'hidden'], $actor, ['object']);
    }

    /**
     * Creates a decided moderation request directing the owner to revise the
     * named section, and journals the action. Unlike a submission routed
     * through `ModerationPipeline`, there is no mode to resolve here — the
     * administrator reviewing the object *is* the decision, made immediately,
     * not a change awaiting one.
     */
    public function returnForRevision(Object_ $object, string $section, string $reason, User $actor): ModerationRequest
    {
        $request = ModerationRequest::create([
            'target_type' => Object_::class,
            'target_id' => $object->id,
            'section' => $section,
            'previous_data' => null,
            'proposed_data' => [],
            'submitted_by' => $actor->id,
            'submitted_at' => now(),
            'assigned_moderator_id' => $actor->id,
            'decision' => 'revision_requested',
            'decided_at' => now(),
            'decided_by' => $actor->id,
            'comment' => $reason,
        ]);

        $this->journal->record('object_returned_for_revision', $object, [], ['section' => $section, 'reason' => $reason], $actor, ['object', 'moderation']);

        return $request;
    }

    public function archive(Object_ $object, User $actor): void
    {
        $object->delete();

        $this->journal->record('object_archived', $object, ['status' => $object->getOriginal('status')], [], $actor, ['object']);
    }

    public function restore(Object_ $object, User $actor): void
    {
        $object->restore();

        $this->journal->record('object_restored', $object, [], [], $actor, ['object']);
    }

    /**
     * Copies the descriptive record — translations, type, geography,
     * attributes — and deliberately nothing commercial: no placement, no
     * financial record, no statistics row travels with the copy. The
     * duplicate starts as a draft with no bump history and no spend, ready to
     * be assigned a package of its own rather than inheriting one.
     */
    public function duplicate(Object_ $object, User $actor): Object_
    {
        return DB::transaction(function () use ($object, $actor): Object_ {
            $copy = $object->replicate(['ulid', 'status', 'moderation_status', 'created_at', 'updated_at']);
            $copy->ulid = (string) Str::ulid();
            $copy->status = 'draft';
            $copy->moderation_status = null;
            $copy->save();

            /** @var ObjectTranslation $translation */
            foreach ($object->translations as $translation) {
                $copy->translations()->create([
                    ...$translation->only(['locale', 'name', 'short_description', 'full_description', 'seo_title', 'seo_description']),
                    'slug' => $translation->slug.'-copy-'.$copy->id,
                ]);
            }

            $this->journal->record('object_duplicated', $object, [], ['duplicate_id' => $copy->id], $actor, ['object']);

            /** @var Object_ $fresh */
            $fresh = $copy->fresh();

            return $fresh;
        });
    }

    /**
     * Reassigns ownership and cleans up what a raw foreign-key flip would
     * leave stale: an `object_user` grant naming the outgoing owner as
     * explicit staff on their own former object. Other staff grants are left
     * alone — access another account was given is independent of who
     * currently owns the object.
     */
    public function transferOwnership(Object_ $object, User $newOwner, User $actor): void
    {
        DB::transaction(function () use ($object, $newOwner, $actor): void {
            $previousOwnerId = $object->owner_id;

            $object->forceFill(['owner_id' => $newOwner->id])->save();

            DB::table('object_user')
                ->where('object_id', $object->id)
                ->where('user_id', $previousOwnerId)
                ->delete();

            $this->journal->record(
                'object_ownership_transferred',
                $object,
                ['owner_id' => $previousOwnerId],
                ['owner_id' => $newOwner->id],
                $actor,
                ['object'],
            );
        });
    }
}
