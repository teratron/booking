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
        $lines = [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /cabinet',
        ];

        $extra = trim((string) $settings->get('seo.robots_extra'));

        if ($extra !== '') {
            $lines[] = $extra;
        }

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain']);
    }
}
