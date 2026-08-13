<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\NewsItem;
use App\Services\Audit\AuditJournal;
use App\Services\Content\ContentPublicationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * The scheduled sweep behind a news item's automatic withdrawal from feeds
 * — dispatched by the scheduler (see routes/console.php), never reachable
 * from a web route.
 *
 * Deliberately distinct from {@see PromotionArchivalJob}: a news item past
 * its own `end_at` withdraws (`status = 'withdrawn'`) rather than
 * archiving — its own detail page stays reachable by slug, only its
 * presence in feeds ends. A promotion's archival is a harder terminal
 * state with no equivalent "still reachable" carve-out, which is why the
 * two content types get two separate jobs rather than one reused for both.
 *
 * Idempotent by construction: the query itself excludes rows already
 * `status = 'withdrawn'`, so a re-run has nothing left to touch for a news
 * item this job already processed.
 */
final class NewsItemWithdrawalJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(AuditJournal $journal, ContentPublicationService $publication): void
    {
        NewsItem::query()
            ->where('status', '!=', 'withdrawn')
            ->whereNotNull('end_at')
            ->where('end_at', '<', now())
            ->chunkById(200, function (Collection $newsItems) use ($journal, $publication): void {
                /** @var NewsItem $newsItem */
                foreach ($newsItems as $newsItem) {
                    $previousStatus = $newsItem->getOriginal('status');
                    $newsItem->status = 'withdrawn';
                    $newsItem->save();

                    $journal->record(
                        'news_item_withdrawn',
                        $newsItem,
                        ['status' => $previousStatus],
                        ['status' => 'withdrawn'],
                        null,
                        ['content', 'news'],
                    );

                    $publication->invalidate(
                        'news',
                        (int) $newsItem->id,
                        $newsItem->object_id,
                        $newsItem->territory_id === null ? [] : [$newsItem->territory_id],
                    );
                }
            });
    }
}
