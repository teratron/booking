<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Shell\PublicShellDataProvider;
use App\Support\Shell\PublicCountryOption;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * The country switcher's own action: stores the visitor's browsing-country
 * preference and navigates to that country's landing page in the current
 * language. The landing route does not exist yet, so the preference is
 * still stored and the visitor simply stays where they are — the same
 * graceful-until-registered pattern this project already uses for links to
 * routes a later task adds.
 */
final class CountryPreferenceController extends Controller
{
    public function __invoke(Request $request, string $lang, PublicShellDataProvider $shellData): RedirectResponse
    {
        $data = $request->validate([
            'country' => ['required', 'string', 'size:2'],
        ]);

        $isActive = in_array(
            $data['country'],
            array_map(static fn (PublicCountryOption $country): string => $country->code, $shellData->activeCountries()),
            true,
        );

        if (! $isActive) {
            abort(404);
        }

        session(['public.country' => $data['country']]);

        if (Route::has('public.territories.show')) {
            return redirect()->route('public.territories.show', ['lang' => $lang, 'country' => $data['country']]);
        }

        return redirect()->back();
    }
}
