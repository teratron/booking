<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Shell\PublicEntryLocaleResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The portal's front door. Every public page lives under a `{lang}`
 * segment, so the bare root has no page of its own — it forwards to the
 * home page of whichever language {@see PublicEntryLocaleResolver} decides
 * the visitor should land in.
 *
 * Deliberately a 302, never a 301: the target is derived from the
 * visitor's own `Accept-Language`, so a permanent redirect would be cached
 * by browsers and intermediaries and pin every later visitor — and every
 * later visit by the same person — to whichever language happened to be
 * negotiated first.
 */
final class RootRedirectController extends Controller
{
    public function __invoke(Request $request, PublicEntryLocaleResolver $locales): RedirectResponse
    {
        return redirect()->route('public.home', ['lang' => $locales->resolve($request)]);
    }
}
