<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Localization\LanguageRegistry;
use App\Services\Shell\PublicShellDataProvider;
use App\Support\Shell\PublicLanguageOption;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * The public API's language negotiation — the `?lang=` counterpart to
 * {@see ResolvePublicLocale}'s mandatory `{lang}` route segment. A request
 * carries no such segment here, so an absent or inactive code falls back to
 * the portal's own primary language rather than 404ing: an API consumer that
 * never sends `?lang=` at all is the expected case, not a malformed request.
 */
final class ResolveApiLocale
{
    public function __construct(
        private readonly PublicShellDataProvider $shellData,
        private readonly LanguageRegistry $languages,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $requested = $request->query('lang');

        $activeCodes = array_map(
            static fn (PublicLanguageOption $language): string => $language->code,
            $this->shellData->activeLanguages(),
        );

        $locale = is_string($requested) && in_array($requested, $activeCodes, true)
            ? $requested
            : $this->languages->primaryLocale();

        App::setLocale($locale);

        return $next($request);
    }
}
