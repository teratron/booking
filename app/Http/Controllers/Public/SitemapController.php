<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateSitemapsJob;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * Serves the sitemap index and its per-language, per-entity-type children
 * — always a plain read of whatever {@see GenerateSitemapsJob}
 * last wrote, never a per-request computation. A 404 here means the job
 * has not run yet, not that the request itself was invalid.
 */
final class SitemapController extends Controller
{
    public function index(): Response
    {
        return $this->serve('sitemaps/sitemap.xml');
    }

    public function child(string $locale, string $filename): Response
    {
        return $this->serve("sitemaps/{$locale}/{$filename}");
    }

    private function serve(string $path): Response
    {
        $disk = Storage::disk((string) config('sitemap.disk'));

        abort_unless($disk->exists($path), 404);

        return response($disk->get($path), 200, ['Content-Type' => 'text/xml']);
    }
}
