<?php

declare(strict_types=1);

namespace App\Services\Cabinet;

use App\Models\Object_;
use App\Models\Promotion;
use App\Models\PromotionTranslation;
use App\Models\Scopes\ModerationScope;
use App\Models\User;
use App\Services\Moderation\ModerationPipeline;
use App\Support\Cabinet\ContentSubmissionOutcome;
use Illuminate\Support\Str;

/**
 * The owner cabinet's promotion-authoring screen: creates a promotion
 * attributed to the current tenant object and routes its authored content
 * through moderation exactly as an object edit does — published
 * immediately, or withheld behind a pending `ModerationRequest`, per the
 * mode resolved for the object's own scope.
 *
 * `starts_at`/`ends_at` are NOT NULL columns on `promotions` (unlike
 * `NewsItem`'s nullable `publish_at`), so both are written at creation
 * regardless of the eventual moderation outcome — that write never exposes
 * anything, since {@see ModerationScope} already hides
 * the whole row until `moderation_status` reaches `approved`. See
 * {@see NewsItemSubmissionService}'s own docblock for
 * why creation and an edit to an already-approved item are deliberately two
 * different code paths here rather than one reused from `ObjectEditService`.
 */
final class PromotionSubmissionService
{
    public function __construct(private readonly ModerationPipeline $moderation) {}

    /**
     * Creates a new, unpublished promotion for $tenant and routes its
     * authored content through moderation.
     *
     * @param  array{title: string, description: ?string, starts_at: string, ends_at: string}  $data
     */
    public function submit(Object_ $tenant, array $data, User $actor): ContentSubmissionOutcome
    {
        $promotion = Promotion::create([
            'object_id' => $tenant->id,
            'territory_id' => $tenant->territory_id,
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'status' => 'draft',
        ]);

        return $this->route($promotion, $tenant, $data, $actor, previousData: null, includesLiveState: true);
    }

    /**
     * Applies an edit to an already-existing promotion, or withholds it —
     * exactly the branch `ObjectEditService::apply()` draws: a not-yet-live
     * promotion's edits always apply directly, and only a change to a
     * promotion that is already published and approved is a moderation
     * candidate.
     *
     * @param  array{title: string, description: ?string, starts_at: string, ends_at: string}  $data
     */
    public function applyEdit(Promotion $promotion, Object_ $tenant, array $data, User $actor): ContentSubmissionOutcome
    {
        if (! $this->isLiveAndApproved($promotion)) {
            $promotion->fill($this->contentFields($promotion, $data));
            $promotion->save();

            return ContentSubmissionOutcome::applied($promotion);
        }

        return $this->route($promotion, $tenant, $data, $actor, previousData: $this->currentSnapshot($promotion), includesLiveState: false);
    }

    private function isLiveAndApproved(Promotion $promotion): bool
    {
        return $promotion->status === 'published' && $promotion->moderation_status === 'approved';
    }

    /**
     * @param  array{title: string, description: ?string, starts_at: string, ends_at: string}  $data
     * @param  ?array<string, mixed>  $previousData
     */
    private function route(Promotion $promotion, Object_ $tenant, array $data, User $actor, ?array $previousData, bool $includesLiveState): ContentSubmissionOutcome
    {
        $proposed = $this->contentFields($promotion, $data);

        if ($includesLiveState) {
            $proposed = ['status' => 'published', 'moderation_status' => 'approved', ...$proposed];
        }

        $outcome = $this->moderation->submit(
            target: $promotion,
            section: 'promotions',
            previousData: $previousData,
            proposedData: $proposed,
            submittedBy: $actor,
            objectId: $tenant->id,
            ownerId: $tenant->owner_id,
            countryId: $tenant->country_id,
        );

        if ($outcome->shouldPublishImmediately) {
            $promotion->fill($proposed);
            $promotion->save();

            return ContentSubmissionOutcome::applied($promotion);
        }

        if ($includesLiveState) {
            // Withheld: the row exists (so it can be moderated and
            // attributed), but carries no translation and no live status
            // until a moderator approves the pending request above — the
            // authored content lives only inside that request's own
            // `proposed_data` until then.
            $promotion->moderation_status = 'pending';
            $promotion->save();
        }

        return ContentSubmissionOutcome::queued($promotion, $outcome->request);
    }

    /** @return array<string, mixed> */
    private function currentSnapshot(Promotion $promotion): array
    {
        $locale = app()->getLocale();
        /** @var ?PromotionTranslation $translation */
        $translation = $promotion->translate($locale, withFallback: false);

        return [
            'starts_at' => $promotion->starts_at->toDateString(),
            'ends_at' => $promotion->ends_at->toDateString(),
            $locale => [
                'title' => $translation?->title,
                'summary' => $translation?->summary,
            ],
        ];
    }

    /**
     * @param  array{title: string, description: ?string, starts_at: string, ends_at: string}  $data
     * @return array<string, mixed>
     */
    private function contentFields(Promotion $promotion, array $data): array
    {
        return [
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            app()->getLocale() => [
                'title' => $data['title'],
                'summary' => $data['description'],
                // This minimal form carries no slug field of its own — it is
                // always derived from the title, suffixed with the row's own
                // id to satisfy the translation table's per-locale
                // uniqueness constraint without a collision check.
                'slug' => Str::slug($data['title']).'-'.$promotion->id,
            ],
        ];
    }
}
