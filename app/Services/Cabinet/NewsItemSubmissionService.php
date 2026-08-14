<?php

declare(strict_types=1);

namespace App\Services\Cabinet;

use App\Models\NewsItem;
use App\Models\NewsTranslation;
use App\Models\Object_;
use App\Models\User;
use App\Services\Moderation\ModerationPipeline;
use App\Support\Cabinet\ContentSubmissionOutcome;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * The owner cabinet's news-authoring screen: creates a news item attributed
 * to the current tenant object and routes its authored content through
 * moderation exactly as an object edit does — published immediately, or
 * withheld behind a pending `ModerationRequest`, per the mode resolved for
 * the object's own scope.
 *
 * A freshly authored item is a genuinely different case from an object edit,
 * which is why this does not reuse `ObjectEditService::apply()`: that method
 * compares a change against an *already-published* record, where
 * `moderation_status` never needs touching because it is already `approved`.
 * Here nothing is published yet, so the very columns that make the row live
 * (`status`, `moderation_status`) must themselves travel inside the proposed
 * snapshot — otherwise a moderator's later approval, which does nothing more
 * than `fill()` the proposed data onto the target, would leave an
 * approved-looking request behind a row that never actually goes live.
 * Editing an item that is already live and approved does not need that (see
 * {@see self::applyEdit()}), which mirrors `ObjectEditService` exactly.
 */
final class NewsItemSubmissionService
{
    public function __construct(private readonly ModerationPipeline $moderation) {}

    /**
     * Creates a new, unpublished news item for $tenant and routes its
     * authored content through moderation.
     *
     * @param  array{title: string, summary: ?string, body: string, publish_at: ?string}  $data
     */
    public function submit(Object_ $tenant, array $data, User $actor): ContentSubmissionOutcome
    {
        $newsItem = NewsItem::create([
            'author_id' => $actor->id,
            'object_id' => $tenant->id,
            'territory_id' => $tenant->territory_id,
            'status' => 'draft',
        ]);

        return $this->route($newsItem, $tenant, $data, $actor, previousData: null, includesLiveState: true);
    }

    /**
     * Applies an edit to an already-existing news item, or withholds it —
     * exactly the branch `ObjectEditService::apply()` draws: a not-yet-live
     * item's edits always apply directly, and only a change to an item that
     * is already published and approved is a moderation candidate.
     *
     * @param  array{title: string, summary: ?string, body: string, publish_at: ?string}  $data
     */
    public function applyEdit(NewsItem $newsItem, Object_ $tenant, array $data, User $actor): ContentSubmissionOutcome
    {
        if (! $this->isLiveAndApproved($newsItem)) {
            $newsItem->fill($this->contentFields($newsItem, $data));
            $newsItem->save();

            return ContentSubmissionOutcome::applied($newsItem);
        }

        return $this->route($newsItem, $tenant, $data, $actor, previousData: $this->currentSnapshot($newsItem), includesLiveState: false);
    }

    private function isLiveAndApproved(NewsItem $newsItem): bool
    {
        return $newsItem->status === 'published' && $newsItem->moderation_status === 'approved';
    }

    /**
     * @param  array{title: string, summary: ?string, body: string, publish_at: ?string}  $data
     * @param  ?array<string, mixed>  $previousData
     */
    private function route(NewsItem $newsItem, Object_ $tenant, array $data, User $actor, ?array $previousData, bool $includesLiveState): ContentSubmissionOutcome
    {
        $proposed = $this->contentFields($newsItem, $data);

        if ($includesLiveState) {
            $proposed = ['status' => 'published', 'moderation_status' => 'approved', ...$proposed];
        }

        $outcome = $this->moderation->submit(
            target: $newsItem,
            section: 'news',
            previousData: $previousData,
            proposedData: $proposed,
            submittedBy: $actor,
            objectId: $tenant->id,
            ownerId: $tenant->owner_id,
            countryId: $tenant->country_id,
        );

        if ($outcome->shouldPublishImmediately) {
            $newsItem->fill($proposed);
            $newsItem->save();

            return ContentSubmissionOutcome::applied($newsItem);
        }

        if ($includesLiveState) {
            // Withheld: the row exists (so it can be moderated and
            // attributed), but carries no translation and no live status
            // until a moderator approves the pending request above — the
            // authored content lives only inside that request's own
            // `proposed_data` until then.
            $newsItem->moderation_status = 'pending';
            $newsItem->save();
        }

        return ContentSubmissionOutcome::queued($newsItem, $outcome->request);
    }

    /** @return array<string, mixed> */
    private function currentSnapshot(NewsItem $newsItem): array
    {
        $locale = app()->getLocale();
        /** @var ?NewsTranslation $translation */
        $translation = $newsItem->translate($locale, withFallback: false);

        return [
            'publish_at' => $newsItem->publish_at?->toIso8601String(),
            $locale => [
                'title' => $translation?->title,
                'summary' => $translation?->summary,
                'body' => $translation?->body,
            ],
        ];
    }

    /**
     * @param  array{title: string, summary: ?string, body: string, publish_at: ?string}  $data
     * @return array<string, mixed>
     */
    private function contentFields(NewsItem $newsItem, array $data): array
    {
        $locale = app()->getLocale();
        $publishAt = $data['publish_at'] !== null ? Carbon::parse($data['publish_at']) : now();

        return [
            'publish_at' => $publishAt->toIso8601String(),
            $locale => [
                'title' => $data['title'],
                'summary' => $data['summary'],
                'body' => $data['body'],
                // This minimal form carries no slug field of its own — it is
                // always derived from the title, suffixed with the row's own
                // id to satisfy the translation table's per-locale
                // uniqueness constraint without a collision check.
                'slug' => Str::slug($data['title']).'-'.$newsItem->id,
            ],
        ];
    }
}
