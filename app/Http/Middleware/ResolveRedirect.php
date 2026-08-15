<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Redirect;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Consults the `redirects` table before a public 404 becomes the response.
 * Wraps the whole `{lang}` route group rather than one page type, since a
 * changed address is not specific to territories or objects — {@see
 * \App\Services\Seo\RedirectRegistrar} is the single place every slug-change
 * call site registers one, whatever kind of page it addresses.
 *
 * Inspects the *response status* from `$next($request)` rather than
 * catching an exception: Laravel's own route-level pipeline
 * (`Illuminate\Routing\Pipeline`) already converts a thrown
 * `NotFoundHttpException` into a rendered 404 response internally, before
 * it would ever reach a `catch` block wrapped around `$next()` here — a
 * try/catch around this call never fires, confirmed by writing and running
 * this task's own test before trusting the shape.
 */
final class ResolveRedirect
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->getStatusCode() !== 404) {
            return $response;
        }

        // Derived from the request URL itself, not a named route parameter
        // — the 404 may come from any public page type (territory, object,
        // or a future one), each with its own route-parameter shape.
        $segments = explode('/', trim($request->path(), '/'), 2);
        $lang = $segments[0];
        $path = $segments[1] ?? '';

        if ($lang === '' || $path === '') {
            return $response;
        }

        $redirect = Redirect::query()
            ->where('locale', $lang)
            ->where('from_path', $path)
            ->where('is_active', true)
            ->first();

        if (! $redirect instanceof Redirect) {
            return $response;
        }

        return redirect(url("/{$lang}/{$redirect->to_path}"), 301);
    }
}
