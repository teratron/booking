<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Seo\SitemapBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Regenerates every sitemap artefact — dispatched by the scheduler (see
 * routes/console.php), never reachable from a web route. The routes that
 * serve sitemaps only ever read what this job last wrote; computing a
 * sitemap per request is not viable at this entity count, and search
 * engines fetch these repeatedly.
 */
final class GenerateSitemapsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(SitemapBuilder $builder): void
    {
        $builder->generate();
    }
}
