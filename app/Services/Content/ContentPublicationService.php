<?php

declare(strict_types=1);

namespace App\Services\Content;

use Illuminate\Support\Facades\Cache;

/**
 * The one place every content type's cache invalidation is enumerated —
 * publishing, scheduling, withdrawing, or archiving an `Article`, a
 * `NewsItem`, or a `Promotion` all change what the home page, the relevant
 * territory page(s), the relevant object page, and the content type's own
 * feed would return, and each of `ArticleLifecycleService`,
 * `NewsItemLifecycleService`, and `PromotionLifecycleService` calls this
 * single method rather than building its own tag list — three independent
 * copies of the same enumeration is exactly the drift a shared pipeline
 * exists to prevent.
 *
 * No `publish()`/`schedule()` entry point lives here: each content type's
 * own lifecycle service already owns that (different field sets — an
 * article has no moderation status, a promotion has no pin flag — make one
 * shared method signature across all three either lossy or a leaky
 * abstraction). What genuinely generalizes across all three is the
 * invalidation contract, so that is what this service owns.
 */
final class ContentPublicationService
{
    /**
     * Flushes every cache tag $contentType's $id could appear under: the
     * type's own feed, its object page when object-scoped, and every
     * related territory page. Safe to call with an empty $territoryIds (a
     * portal-wide item with nothing to narrow by) or a null $objectId (not
     * object-scoped) — both are optional dimensions, never a required one.
     *
     * @param  'article'|'news'|'promotion'  $contentType
     * @param  list<int>  $territoryIds
     */
    public function invalidate(string $contentType, int $id, ?int $objectId, array $territoryIds): void
    {
        $tags = ['content', "{$contentType}:{$id}", "{$contentType}s:feed"];

        if ($objectId !== null) {
            $tags[] = "object:{$objectId}";
        }

        foreach ($territoryIds as $territoryId) {
            $tags[] = "territory:{$territoryId}";
        }

        Cache::tags($tags)->flush();
    }
}
