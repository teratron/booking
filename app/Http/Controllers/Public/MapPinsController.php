<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Object_;
use App\Models\Territory;
use App\Services\Catalog\CatalogQueryService;
use App\Services\Catalog\ObjectCardPresenter;
use App\Support\Catalog\CatalogSearchCriteria;
use App\Support\Catalog\MapBounds;
use App\Support\Catalog\MapPin;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The catalog map's own retrieval surface: a lean list of pins within the
 * visible viewport, and — fetched separately, on click — the compact card
 * for one pin.
 */
final class MapPinsController extends Controller
{
    public function __construct(
        private readonly CatalogQueryService $catalog,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $bounds = new MapBounds(
            southWestLat: (float) $request->query('sw_lat'),
            southWestLng: (float) $request->query('sw_lng'),
            northEastLat: (float) $request->query('ne_lat'),
            northEastLng: (float) $request->query('ne_lng'),
        );

        $criteria = new CatalogSearchCriteria(
            territory: $request->filled('territory_id') ? Territory::query()->find($request->integer('territory_id')) : null,
            countryId: $request->filled('country_id') ? $request->integer('country_id') : null,
            objectTypeId: $request->filled('type') ? $request->integer('type') : null,
            name: $request->filled('q') ? $request->string('q')->value() : null,
        );

        $pins = $this->catalog->pins($criteria, $bounds);

        return response()->json([
            'pins' => array_map(static fn (MapPin $pin): array => [
                'id' => $pin->objectId,
                'lat' => $pin->lat,
                'lng' => $pin->lng,
                'tier_border_colour' => $pin->tierBorderColour,
            ], $pins),
        ]);
    }

    /**
     * `$lang` is unused but must stay declared, matching its position in
     * the route: Laravel's controller dependency resolver splices
     * container-resolved parameters (here, `$presenter`) into the route's
     * own parameter list by reflection position, and a leading route
     * segment with no corresponding method parameter throws that splice
     * out of alignment, silently handing `$object` the raw `lang` string
     * instead of the resolved model.
     */
    public function show(string $lang, Object_ $object, ObjectCardPresenter $presenter): View
    {
        abort_unless($object->status === 'published', 404);

        return view('components.public.map-pin-card', ['card' => $presenter->present($object)]);
    }
}
