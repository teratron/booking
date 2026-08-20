<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Promotion;
use App\Services\Audit\AuditJournal;
use App\Services\Content\ContentPublicationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * The scheduled sweep behind a promotion's automatic archival — dispatched
 * by the scheduler (see routes/console.php), never reachable from a web
 * route. A promotion whose `ends_at` has passed archives once its end date
 * is reached; a render-time check would leave an expired promotion live in
 * every cache that has not yet turned over, which is exactly what this job
 * exists to avoid.
 *
 * Idempotent by construction: the query itself excludes rows already
 * `status = 'archived'`, so a re-run has nothing left to touch for a
 * promotion this job already processed.
 */
final class PromotionArchivalJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** A daily lifecycle sweep — see config/horizon.php's own "default" supervisor. */
    public function __construct()
    {
        $this->onQueue('default');
    }

    public function handle(AuditJournal $journal, ContentPublicationService $publication): void
    {
        Promotion::query()
            ->where('status', '!=', 'archived')
            ->where('ends_at', '<', now()->toDateString())
            ->chunkById(200, function (Collection $promotions) use ($journal, $publication): void {
                /** @var Promotion $promotion */
                foreach ($promotions as $promotion) {
                    $previousStatus = $promotion->getOriginal('status');
                    $promotion->status = 'archived';
                    $promotion->save();

                    $journal->record(
                        'promotion_archived',
                        $promotion,
                        ['status' => $previousStatus],
                        ['status' => 'archived'],
                        null,
                        ['content', 'promotion'],
                    );

                    $publication->invalidate('promotion', (int) $promotion->id, (int) $promotion->object_id, [(int) $promotion->territory_id]);
                }
            });
    }
}
