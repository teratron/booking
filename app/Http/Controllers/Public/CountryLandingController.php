<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Services\Seo\PublicUrlGenerator;
use Illuminate\Http\RedirectResponse;

/**
 * The bare country segment (`/{lang}/{country}`) is not its own page
 * composition — a country is a dimension of the territory tree, not one
 * specific node in it ({@see CountryPreferenceController}'s own established
 * reading) — so it redirects to that country's own top-level territory with
 * the lowest display order, the same target the country switcher computes.
 */
final class CountryLandingController extends Controller
{
    public function __construct(private readonly PublicUrlGenerator $urls) {}

    public function __invoke(string $lang, string $country): RedirectResponse
    {
        $record = Country::query()->where('code', strtoupper($country))->where('is_active', true)->first();

        abort_unless($record instanceof Country, 404);

        $target = $this->urls->countryLandingUrl($record, $lang);

        abort_unless($target !== null, 404);

        return redirect($target, 301);
    }
}
