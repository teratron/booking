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
            territory: self::queryFilled($request, 'territory_id') ? Territory::query()->find($request->integer('territory_id')) : null,
            countryId: self::queryFilled($request, 'country_id') ? $request->integer('country_id') : null,
            objectTypeId: self::queryFilled($request, 'type') ? $request->integer('type') : null,
            name: self::queryFilled($request, 'q') ? $request->string('q')->value() : null,
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

    /**
     * `Request::filled()` treats the four-character string `"null"` as a
     * real, non-empty value — which is exactly what it is once the browser
     * JS side has serialised a PHP `null` into a query string via
     * `URLSearchParams`. The catalog map's own filter dispatch does this on
     * every render in its default (no-filter) state, so `type=null&q=null`
     * arriving verbatim is the *common* case here, not an edge case; without
     * this guard `(int) "null"` resolves to `0`, a type id nothing matches,
     * and the map shows zero pins. Guards every caller of this endpoint, not
     * only the one Livewire component that happens to trigger it today.
     */
    private static function queryFilled(Request $request, string $key): bool
    {
        if (! $request->filled($key)) {
            return false;
        }

        return $request->string($key)->value() !== 'null';
    }
}
