<?php

declare(strict_types=1);

namespace App\Services\Shell;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;

/**
 * Computes the target URL for the language switcher: the same route and
 * parameters the visitor is already on, with only the `lang` segment
 * swapped, so switching language preserves position rather than returning
 * to the home page.
 *
 * Per-entity slug translation — resolving a *different* slug for the same
 * territory or object in the target language, with a fallback to the
 * nearest translated ancestor when one is missing — is a concern of
 * whatever later builds per-language slugs onto object and territory
 * routes; it does not exist yet, so this resolver only ever swaps the
 * `lang` segment of a route that is language-neutral by construction,
 * which is the only kind of public route currently registered.
 */
final class LocaleSwitchResolver
{
    public function targetUrl(Request $request, string $targetLang): string
    {
        $route = $request->route();

        if (! $route instanceof Route || $route->getName() === null) {
            return url("/{$targetLang}");
        }

        $parameters = $route->parameters();
        $parameters['lang'] = $targetLang;

        $url = route($route->getName(), $parameters);
        $query = $request->query();

        return $query === [] ? $url : $url.'?'.http_build_query($query);
    }
}
