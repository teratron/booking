<?php

declare(strict_types=1);

namespace App\Services\Objects;

use App\Exceptions\ObjectMergeRefusedException;
use App\Models\Object_;
use App\Models\ObjectPlacement;
use App\Models\ObjectTranslation;
use App\Models\StatDaily;
use App\Models\StatEvent;
use App\Models\User;
use App\Services\Audit\AuditJournal;
use App\Services\Seo\PublicUrlGenerator;
use App\Services\Seo\RedirectRegistrar;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The one place duplicate objects already sitting in the catalog are ever
 * combined into one. Duplicate detection during import ({@see
 * \App\Services\DataTransfer\DuplicateDetectionService}) only ever
 * *presents* a candidate pairing — it writes nothing — so by the time two
 * records genuinely need combining, both already exist as ordinary,
 * persisted objects an administrator picked deliberately. This service does
 * not decide which one; it only carries out the decision.
 *
 * Everything downstream of the merged-away record moves to the survivor in
 * one transaction: its photo gallery, its commercial placement (when the
 * survivor does not already hold one of its own), every analytics row that
 * named it, and a permanent redirect from its own public URL to the
 * survivor's. The merged-away record itself is archived, never force-
 * deleted — the same soft-delete every other archival path in this codebase
 * uses, so it stays reachable for the journal, for any later export/import
 * round-trip, and for a chief administrator's own manual review.
 */
final class ObjectMergeService
{
    public function __construct(
        private readonly AuditJournal $journal,
        private readonly RedirectRegistrar $redirects,
        private readonly PublicUrlGenerator $urls,
    ) {}

    /**
     * @throws ObjectMergeRefusedException when the two records are the same
     *                                     row, or when either side is already archived
     */
    public function merge(Object_ $survivor, Object_ $mergedAway, User $actor): void
    {
        if ($survivor->is($mergedAway)) {
            throw ObjectMergeRefusedException::sameRecord($survivor->id);
        }

        if ($survivor->trashed()) {
            throw ObjectMergeRefusedException::alreadyArchived($survivor->id, 'survivor');
        }

        if ($mergedAway->trashed()) {
            throw ObjectMergeRefusedException::alreadyArchived($mergedAway->id, 'merged-away record');
        }

        // Strict mode (`Model::shouldBeStrict()`) throws on an unloaded
        // relation touched through the magic property accessor — both the
        // translated `name` attribute below and `registerRedirects()`'s own
        // iteration over `$mergedAway->translations` go through exactly
        // that accessor, so both records load it explicitly up front rather
        // than each private method re-deriving whether it is already there.
        $survivor->loadMissing('translations');
        $mergedAway->loadMissing('translations');

        // Snapshotted before anything moves — the journal entry must name
        // both records as they stood at the moment of the merge, not as
        // whatever the survivor becomes after absorbing the loser's data.
        $survivorIdentity = $this->identitySnapshot($survivor);
        $mergedAwayIdentity = $this->identitySnapshot($mergedAway);

        DB::transaction(function () use ($survivor, $mergedAway, $actor, $survivorIdentity, $mergedAwayIdentity): void {
            $this->reattachMedia($survivor, $mergedAway);
            $this->reattachPlacement($survivor, $mergedAway);
            $this->reattachStatistics($survivor, $mergedAway);
            $this->registerRedirects($survivor, $mergedAway);

            $mergedAway->delete();

            $this->journal->record(
                'object_merged',
                $survivor,
                ['merged_away' => $mergedAwayIdentity],
                ['survivor' => $survivorIdentity],
                $actor,
                ['object', 'merge'],
            );
        });
    }

    /** @return array<string, mixed> */
    private function identitySnapshot(Object_ $object): array
    {
        return [
            'id' => $object->id,
            'ulid' => $object->ulid,
            'name' => $object->name,
        ];
    }

    /**
     * A plain attribute move, not a re-upload: `model_type` is already
     * identical on both sides (both rows are {@see Object_}), so only
     * `model_id` changes. Bypassing Eloquent events here is deliberate — no
     * conversion needs regenerating, only the ownership pointer moves.
     */
    private function reattachMedia(Object_ $survivor, Object_ $mergedAway): void
    {
        $mergedAway->media()->update(['model_id' => $survivor->id]);
    }

    /**
     * `object_placements.object_id` is unique — one commercial grant per
     * object — so a placement can move to the survivor only when the
     * survivor does not already hold one of its own. When both records
     * carry a placement, the survivor's own paid grant is left untouched
     * rather than silently overwritten by whichever object happened to be
     * the one merged away; the loser's placement row is left pointing at
     * the now-archived object, invisible to the catalog like everything
     * else that stays behind on a merged-away record.
     */
    private function reattachPlacement(Object_ $survivor, Object_ $mergedAway): void
    {
        if (ObjectPlacement::query()->where('object_id', $survivor->id)->exists()) {
            return;
        }

        ObjectPlacement::query()
            ->where('object_id', $mergedAway->id)
            ->update(['object_id' => $survivor->id]);
    }

    /**
     * Raw interaction rows (`stat_events`) carry no uniqueness constraint on
     * their subject, so every row simply repoints to the survivor. The
     * aggregated `stat_dailies` tier is different: it is unique on
     * `(date, subject_type, subject_id, kind, contact_channel_type_id,
     * territory_id, locale)`, so a rollup row that would collide with one
     * the survivor already owns for the same day is folded into it — counts
     * summed — instead of repointed, which would violate that constraint.
     */
    private function reattachStatistics(Object_ $survivor, Object_ $mergedAway): void
    {
        StatEvent::query()
            ->where('subject_type', Object_::class)
            ->where('subject_id', $mergedAway->id)
            ->update(['subject_id' => $survivor->id]);

        foreach ($this->loserRollups($mergedAway) as $loserRollup) {
            $survivorRollup = StatDaily::query()
                ->where('subject_type', Object_::class)
                ->where('subject_id', $survivor->id)
                ->where('date', $loserRollup->date)
                ->where('kind', $loserRollup->kind)
                ->where('contact_channel_type_id', $loserRollup->contact_channel_type_id)
                ->where('territory_id', $loserRollup->territory_id)
                ->where('locale', $loserRollup->locale)
                ->first();

            if ($survivorRollup instanceof StatDaily) {
                $survivorRollup->increment('count', $loserRollup->count);
                $loserRollup->delete();

                continue;
            }

            $loserRollup->update(['subject_id' => $survivor->id]);
        }
    }

    /** @return Collection<int, StatDaily> */
    private function loserRollups(Object_ $mergedAway): Collection
    {
        return StatDaily::query()
            ->where('subject_type', Object_::class)
            ->where('subject_id', $mergedAway->id)
            ->get();
    }

    /**
     * Writes a redirect for every language the merged-away object carried a
     * translation in, targeting {@see PublicUrlGenerator::objectUrl()}'s own
     * grammar for the survivor — restated locally rather than injected, the
     * same choice `ModerationDecisionService::registerObjectSlugRedirects()`
     * already made, since building it needs nothing beyond the slug itself.
     * A locale the survivor carries no translation for is skipped rather
     * than redirected to a page that would not resolve; `objectUrl()` is
     * consulted as a guard so the target is always the survivor's real,
     * current URL, never a guessed one.
     */
    private function registerRedirects(Object_ $survivor, Object_ $mergedAway): void
    {
        /** @var ObjectTranslation $translation */
        foreach ($mergedAway->translations as $translation) {
            $locale = $translation->locale;
            $oldSlug = $translation->slug;
            $newSlug = $survivor->translations()->where('locale', $locale)->value('slug');

            if ($newSlug === null) {
                continue;
            }

            if ($this->urls->objectUrl($survivor, $locale) === null) {
                continue;
            }

            $this->redirects->registerPathChange($locale, "o/{$oldSlug}", "o/{$newSlug}");
        }
    }
}
