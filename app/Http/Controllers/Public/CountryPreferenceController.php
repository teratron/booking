<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Services\Seo\PublicUrlGenerator;
use App\Services\Shell\PublicShellDataProvider;
use App\Support\Shell\PublicCountryOption;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The country switcher's own action: stores the visitor's browsing-country
 * preference and navigates to that country's landing page in the current
 * language. There is no single "country root" territory record — a country
 * is a dimension of its own, not one specific node in the territory tree —
 * so the landing target is that country's own top-level territory with the
 * lowest display order, the closest real equivalent; if the country has no
 * territory yet, the preference is still stored and the visitor stays
 * where they are. {@see CountryLandingController}
 * resolves the same target for a visitor arriving at the bare country URL
 * directly, rather than through this switcher.
 */
final class CountryPreferenceController extends Controller
{
    public function __invoke(Request $request, string $lang, PublicShellDataProvider $shellData, PublicUrlGenerator $urls): RedirectResponse
    {
        $data = $request->validate([
            'country' => ['required', 'string', 'size:2'],
        ]);

        $country = collect($shellData->activeCountries())
            ->first(fn (PublicCountryOption $option): bool => $option->code === $data['country']);

        if (! $country instanceof PublicCountryOption) {
            abort(404);
        }

        session(['public.country' => $data['country']]);

        $countryRecord = Country::query()->find($country->id);
        $target = $countryRecord instanceof Country ? $urls->countryLandingUrl($countryRecord, $lang) : null;

        if ($target !== null) {
            return redirect($target);
        }

        return redirect()->back();
    }
}
