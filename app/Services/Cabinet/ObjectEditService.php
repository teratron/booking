<?php

declare(strict_types=1);

namespace App\Services\Cabinet;

use App\Models\ModerationRequest;
use App\Models\Object_;
use App\Models\ObjectTranslation;
use App\Models\User;
use App\Services\Moderation\ModerationPipeline;
use App\Support\Cabinet\ObjectEditOutcome;

/**
 * Applies an owner's edit to one object, or withholds it pending review.
 *
 * The content this service governs is exactly what {@see Object_::fill()}
 * and its cascading translation save can correctly reconstruct on its own
 * from a plain snapshot: the direct columns (`address`, `latitude`,
 * `longitude`, `country_id`, `territory_id`, `attributes`) and the
 * per-language translated fields, keyed by locale code at the top level —
 * `astrotomic/laravel-translatable`'s own `fill()` override recognises a
 * top-level key that names an active locale and routes it to that locale's
 * translation row, regardless of which locale is currently pinned. This is
 * deliberately the *entire* set `ModerationDecisionService::approve()` can
 * safely replay later (it does nothing more than `$target->fill($data);
 * $target->save();`) — contact channels are a separate `hasMany` relation
 * with no matching column, so a proposed change naming them would either be
 * silently dropped or throw when a moderator later approves it. Contact
 * editing is therefore handled entirely outside this service, applying
 * immediately regardless of publication state (see the cabinet edit page).
 *
 * Only an edit to an object that is *already published* is a moderation
 * candidate. A draft (or hidden, or archived) object has nothing live for
 * moderation to protect yet, so every edit to it applies directly — the same
 * reasoning that makes object creation itself direct, before this service is
 * ever consulted.
 */
final class ObjectEditService
{
    public function __construct(private readonly ModerationPipeline $moderation) {}

    /**
     * The object's current content, shaped exactly as {@see self::apply()}
     * expects both snapshots — read before any proposed change is applied,
     * so the comparison and the eventual moderator review both see what was
     * genuinely live at submission time.
     *
     * @return array<string, mixed>
     */
    public function snapshot(Object_ $object): array
    {
        $snapshot = $object->only(['address', 'latitude', 'longitude', 'country_id', 'territory_id', 'attributes']);

        foreach ($object->translations as $translation) {
            /** @var ObjectTranslation $translation */
            $snapshot[$translation->locale] = $translation->only([
                'name', 'short_description', 'full_description', 'slug', 'seo_title', 'seo_description',
                'seo_canonical_url', 'seo_indexable', 'seo_og_title', 'seo_og_description', 'seo_og_image',
            ]);
        }

        return $snapshot;
    }

    /**
     * Decides whether $proposed may be written now.
     *
     * A no-op save (nothing actually differs from $current) never reaches
     * the pipeline at all — resubmitting an untouched form must not manufacture
     * a moderation request. Otherwise: an edit to a draft (or otherwise
     * unpublished) object always applies; an edit to a published object is
     * routed through {@see ModerationPipeline::submit()}, and this method's
     * result tells the caller whether to actually perform the write.
     *
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $proposed
     */
    public function apply(Object_ $object, array $current, array $proposed, User $actor): ObjectEditOutcome
    {
        if ($this->normalize($current) === $this->normalize($proposed)) {
            return ObjectEditOutcome::applied();
        }

        if ($object->status !== 'published') {
            return ObjectEditOutcome::applied();
        }

        $outcome = $this->moderation->submit(
            target: $object,
            section: $this->resolveSection($current, $proposed),
            previousData: $current,
            proposedData: $proposed,
            submittedBy: $actor,
            objectId: $object->id,
            ownerId: $object->owner_id,
            categoryId: $object->object_type_id,
            countryId: $object->country_id,
        );

        return $outcome->shouldPublishImmediately
            ? ObjectEditOutcome::applied()
            : ObjectEditOutcome::queued($outcome->request);
    }

    /**
     * The most recent moderation request an owner still needs to see and
     * possibly act on — pending, rejected, or sent back for revision.
     * Requests already approved carry nothing actionable: the live record
     * now matches what was proposed, so there is nothing left to surface.
     */
    public function latestActionableRequest(Object_ $object): ?ModerationRequest
    {
        return ModerationRequest::query()
            ->where('target_type', Object_::class)
            ->where('target_id', $object->id)
            ->whereIn('decision', ['pending', 'rejected', 'revision_requested'])
            ->latest('submitted_at')
            ->first();
    }

    /**
     * Picks the single change-type key that best represents this save, from
     * the portal's own configured vocabulary — checked in an order that
     * favours the more visible, more frequently-moderated categories first.
     * Every field this service governs ends up filed under one of these four
     * keys; when only fields outside that vocabulary changed (type-specific
     * attributes, SEO metadata), 'name' is the fallback label, matching this
     * portal's own moderated-change-type registry rather than inventing a
     * new one no administrator setting could ever reference.
     *
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $proposed
     */
    private function resolveSection(array $current, array $proposed): string
    {
        $normalizedCurrent = $this->normalize($current);
        $normalizedProposed = $this->normalize($proposed);

        foreach ($normalizedProposed as $locale => $fields) {
            if (! is_array($fields)) {
                continue;
            }

            $previousFields = is_array($normalizedCurrent[$locale] ?? null) ? $normalizedCurrent[$locale] : [];

            if (($fields['name'] ?? null) !== ($previousFields['name'] ?? null)) {
                return 'name';
            }
        }

        foreach ($normalizedProposed as $locale => $fields) {
            if (! is_array($fields)) {
                continue;
            }

            $previousFields = is_array($normalizedCurrent[$locale] ?? null) ? $normalizedCurrent[$locale] : [];

            if (($fields['short_description'] ?? null) !== ($previousFields['short_description'] ?? null)
                || ($fields['full_description'] ?? null) !== ($previousFields['full_description'] ?? null)) {
                return 'description';
            }
        }

        foreach (['address', 'latitude', 'longitude', 'country_id', 'territory_id'] as $field) {
            if (($normalizedProposed[$field] ?? null) !== ($normalizedCurrent[$field] ?? null)) {
                return 'location';
            }
        }

        return 'name';
    }

    /**
     * Reduces every scalar leaf to a comparable form so a decimal column's
     * string representation ("45.1230000"), a freshly-typed form value
     * ("45.123"), and an integer foreign key submitted as a numeric string
     * from the request all compare equal when they represent the same value.
     * Recurses into the per-locale sub-arrays this snapshot shape nests.
     *
     * Also drops any array-valued entry whose every element normalizes to
     * null. A locale with no translation row yet is simply absent from
     * {@see self::snapshot()}'s output; the same locale's form section,
     * left entirely untouched, dehydrates as a bucket of nulls rather than
     * disappearing. Without this, those two representations of "nothing
     * here" would compare unequal, and every save would look like a change
     * to a language the owner never opened.
     *
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function normalize(array $snapshot): array
    {
        $normalized = array_map(
            fn (mixed $value): mixed => match (true) {
                is_array($value) => $this->normalize($value),
                $value === null || $value === '' => null,
                is_numeric($value) => (float) $value,
                default => (string) $value,
            },
            $snapshot,
        );

        return array_filter(
            $normalized,
            fn (mixed $value): bool => ! (is_array($value) && array_filter($value, fn (mixed $leaf): bool => $leaf !== null) === []),
        );
    }
}
