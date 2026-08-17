<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Settings\SettingsRepository;
use Illuminate\Http\Response;

/**
 * The single portal-wide `robots.txt` — not per-language, since the
 * directory it disallows (the owner cabinet and back office) is itself
 * language-agnostic. Per-page indexability for public content is decided
 * by the `noindex` meta tag on that page, not by an entry here: a
 * `Disallow` line prevents a crawler from ever fetching the page at all,
 * which would hide the very `noindex` signal a filtered catalog view
 * relies on rather than announcing it.
 */
final class RobotsController extends Controller
{
    public function __invoke(SettingsRepository $settings): Response
    {
        // The staff panel's own path is deliberately non-guessable
        // (`config/booking.php`'s own security rationale) — a hardcoded
        // `/admin` here would both miss the real, reachable path and
        // publish a wrong guess for it, defeating that obscurity layer
        // instead of reinforcing it.
        $lines = [
            'User-agent: *',
            'Disallow: /'.config('booking.panels.admin.path'),
            'Disallow: /'.config('booking.panels.cabinet.path'),
        ];

        $extra = trim((string) $settings->get('seo.robots_extra'));

        if ($extra !== '') {
            $lines[] = $extra;
        }

        $lines[] = 'Sitemap: '.route('public.sitemaps.index');

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain']);
    }
}
