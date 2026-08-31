<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Http\Controllers\Public\SitemapController;
use App\Jobs\GenerateSitemapsJob;
use App\Services\Seo\SitemapBuilder;
use Illuminate\Console\Command;

/**
 * Regenerates every sitemap artefact synchronously — the same work
 * {@see GenerateSitemapsJob} does on the hourly schedule, exposed
 * as a command so the deployment sequence can regenerate it inline after
 * migrations rather than waiting for the first scheduled run
 * ({@see SitemapController} otherwise serves a
 * 503 until then).
 */
final class GenerateSitemaps extends Command
{
    protected $signature = 'sitemap:generate';

    protected $description = 'Regenerate the sitemap index and its per-language, per-entity-type children.';

    public function handle(SitemapBuilder $builder): int
    {
        // Building every object URL for a seeded-volume catalog needs more
        // headroom than the CLI's own 128 MB default — the same reason the
        // benchmark and test commands raise it. The hourly queued job runs
        // under Horizon's own (larger) worker limit.
        ini_set('memory_limit', '512M');

        $builder->generate();

        $this->info('Sitemap artefacts regenerated.');

        return self::SUCCESS;
    }
}
