<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Shell\PublicShellDataProvider;
use App\Support\Shell\PublicLanguageOption;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * The public site's `{lang}` route segment is the first and authoritative
 * source of the request's language — no other public route exists outside
 * a locale-prefixed group, so every request this middleware sees carries
 * one. A segment that does not match an active language code 404s rather
 * than silently falling back: an invented `/xx/...` URL is not a valid
 * address, and falling back would make every inactive or misspelled code a
 * working, indexable duplicate of the real page.
 *
 * Resolving a language with no URL segment at all (a stored session
 * preference, the `Accept-Language` header, then the primary-language
 * fallback) belongs to whichever page is reachable without one, which does
 * not exist yet; it is deliberately not built here until that page does.
 */
final class ResolvePublicLocale
{
    public function __construct(
        private readonly PublicShellDataProvider $shellData,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $lang = (string) $request->route('lang');

        $isActive = in_array(
            $lang,
            array_map(static fn (PublicLanguageOption $language): string => $language->code, $this->shellData->activeLanguages()),
            true,
        );

        if (! $isActive) {
            abort(404);
        }

        App::setLocale($lang);

        return $next($request);
    }
}
