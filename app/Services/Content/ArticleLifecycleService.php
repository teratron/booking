<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Exceptions\ArticleScheduleRefusedException;
use App\Models\Article;
use App\Models\User;
use App\Services\Audit\AuditJournal;
use Illuminate\Support\Carbon;

/**
 * The article form's lifecycle actions — everything beyond an ordinary field
 * save.
 *
 * Articles carry no `moderation_status` column and never enter the review
 * queue the way owner-submitted objects do — an administrator authoring or
 * editing one is already the trusted act. Every method here therefore
 * mutates the record immediately and journals what it did.
 */
final class ArticleLifecycleService
{
    public function __construct(
        private readonly AuditJournal $journal,
        private readonly ContentPublicationService $publication,
    ) {}

    /**
     * Returns $article to draft, clearing any prior schedule — a draft with
     * a stale `publish_at` would otherwise read as scheduled the moment its
     * status changes back.
     */
    public function saveAsDraft(Article $article, User $actor): void
    {
        $previousStatus = $article->getOriginal('status');
        $article->status = 'draft';
        $article->publish_at = null;
        $article->save();

        $this->journal->record('article_saved_as_draft', $article, ['status' => $previousStatus], ['status' => 'draft'], $actor, ['content']);

        $this->invalidateContentCaches($article);
    }

    /**
     * Marks $article to go live at $publishAt without publishing it now.
     *
     * @throws ArticleScheduleRefusedException when $publishAt is not in the future
     */
    public function schedule(Article $article, Carbon $publishAt, User $actor): void
    {
        if (! $publishAt->isFuture()) {
            throw ArticleScheduleRefusedException::notInFuture((int) $article->id);
        }

        $previousStatus = $article->getOriginal('status');
        $previousPublishAt = $this->originalPublishAt($article);

        $article->status = 'scheduled';
        $article->publish_at = $publishAt;
        $article->save();

        $this->journal->record(
            'article_scheduled',
            $article,
            ['status' => $previousStatus, 'publish_at' => $previousPublishAt],
            ['status' => 'scheduled', 'publish_at' => $publishAt->toIso8601String()],
            $actor,
            ['content'],
        );

        $this->invalidateContentCaches($article);
    }

    /**
     * Publishes $article immediately. A `publish_at` left unset, or still in
     * the future from a prior schedule, is brought forward to now — the
     * action means "make this live", not "keep whatever date was there".
     */
    public function publish(Article $article, User $actor): void
    {
        $previousStatus = $article->getOriginal('status');
        $previousPublishAt = $this->originalPublishAt($article);

        $article->status = 'published';

        if ($article->publish_at === null || $article->publish_at->isFuture()) {
            // Carbon::now(), not the now() helper: the helper's declared
            // return type is the broader CarbonInterface, which does not
            // statically satisfy the model's concrete Carbon attribute type.
            $article->publish_at = Carbon::now();
        }

        $article->save();

        $this->journal->record(
            'article_published',
            $article,
            ['status' => $previousStatus, 'publish_at' => $previousPublishAt],
            ['status' => 'published', 'publish_at' => $article->publish_at->toIso8601String()],
            $actor,
            ['content'],
        );

        $this->invalidateContentCaches($article);
    }

    /**
     * Soft-deletes $article. The published record is never hard-removed by
     * this method — recoverable via {@see restore()} for as long as the row
     * stays soft-deleted.
     */
    public function archive(Article $article, User $actor): void
    {
        $previousStatus = $article->getOriginal('status');
        $article->delete();

        $this->journal->record('article_archived', $article, ['status' => $previousStatus], [], $actor, ['content']);

        $this->invalidateContentCaches($article);
    }

    public function restore(Article $article, User $actor): void
    {
        $article->restore();

        $this->journal->record('article_restored', $article, [], [], $actor, ['content']);

        $this->invalidateContentCaches($article);
    }

    private function originalPublishAt(Article $article): ?string
    {
        $original = $article->getOriginal('publish_at');

        if ($original instanceof Carbon) {
            return $original->toIso8601String();
        }

        return $original === null ? null : (string) $original;
    }

    /**
     * Publishing, scheduling, archiving, and restoring all change what a
     * public-facing read of this article — and of the territories it
     * relates to — would return. Delegates the actual tag enumeration to
     * {@see ContentPublicationService}, the one place all three content
     * types' invalidation keys are enumerated, rather than repeating the
     * list here.
     */
    private function invalidateContentCaches(Article $article): void
    {
        $this->publication->invalidate(
            'article',
            (int) $article->id,
            null,
            array_values($article->territories->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all()),
        );
    }
}
