<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateSitemapsJob;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * Serves the sitemap index and its per-language, per-entity-type children
 * — always a plain read of whatever {@see GenerateSitemapsJob} last wrote,
 * never a per-request computation.
 *
 * The index is guarded on freshness: missing (a fresh deploy that has run
 * no generation job yet) or older than `sitemap.max_age_hours` is treated
 * as absent — a regeneration is dispatched and the request answered `503`
 * with a `Retry-After`, rather than a crawler being served an empty or
 * stale sitemap. Children are a plain read: they only exist because the
 * index that references them does.
 */
final class SitemapController extends Controller
{
    public function index(): Response
    {
        $disk = Storage::disk((string) config('sitemap.disk'));
        $path = 'sitemaps/sitemap.xml';

        $maxAgeSeconds = (int) config('sitemap.max_age_hours', 6) * 3600;
        $stale = ! $disk->exists($path)
            || (time() - $disk->lastModified($path)) > $maxAgeSeconds;

        if ($stale) {
            GenerateSitemapsJob::dispatch();

            return response(
                "The sitemap is being regenerated. Retry shortly.\n",
                503,
                ['Content-Type' => 'text/plain', 'Retry-After' => '120'],
            );
        }

        return response($disk->get($path), 200, ['Content-Type' => 'text/xml']);
    }

    public function child(string $locale, string $filename): Response
    {
        $disk = Storage::disk((string) config('sitemap.disk'));
        $path = "sitemaps/{$locale}/{$filename}";

        abort_unless($disk->exists($path), 404);

        return response($disk->get($path), 200, ['Content-Type' => 'text/xml']);
    }
}
