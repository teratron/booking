<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Banner;
use App\Models\Country;
use App\Models\NewsItem;
use App\Models\Object_;
use App\Models\Promotion;
use App\Models\Review;
use App\Models\Territory;
use App\Services\Advertising\BannerSelectionService;
use App\Services\Catalog\CatalogQueryService;
use App\Services\Shell\PublicShellDataProvider;
use App\Support\Catalog\CatalogSearchCriteria;
use App\Support\Shell\PublicNavigationEntry;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The home page: sixteen blocks, each a view onto data another spec owns
 * — this page stores nothing of its own. Every block is resolved in this
 * one action and omitted independently when it has nothing to show; every
 * computed/object-listing block resolves against the visitor's selected
 * country.
 */
final class HomePageController extends Controller
{
    private const int OBJECTS_PER_RAIL = 8;

    private const int NEWS_PER_RAIL = 4;

    private const int PROMOTIONS_PER_RAIL = 4;

    private const int ARTICLES_PER_RAIL = 4;

    private const int REVIEWS_PER_RAIL = 6;

    public function __construct(
        private readonly CatalogQueryService $catalog,
        private readonly PublicShellDataProvider $shell,
        private readonly BannerSelectionService $banners,
    ) {}

    public function show(string $lang): View
    {
        $country = $this->resolveCountry();
        // The home page has no territory of its own — a visitor's selected
        // country stands in for one, via its own top-level landing territory,
        // so a banner targeted geographically can surface here too, not only
        // on the territory pages that already resolve a real Territory.
        $territory = $this->resolveTopLevelTerritory($country);
        $bannerContext = ['territory' => $territory, 'language' => app()->getLocale()];

        return view('public.home.show', [
            'country' => $country,
            'popularDestinations' => $this->shell->popularDestinations($country?->id),
            'categoryTiles' => $this->categoryTiles($country),
            'recommendedObjects' => $this->objectsRail($country),
            'newestObjects' => $this->objectsRail($country, createdAfter: now()->subDays(90)),
            'promotions' => $this->promotions($country),
            'newsItems' => $this->newsItems($country),
            'articles' => Article::published()->with(['translations', 'media'])->latest('publish_at')->limit(self::ARTICLES_PER_RAIL)->get(),
            'popularCities' => $this->shell->popularCities($country?->id),
            'reviews' => $this->reviews($country),
            'partners' => Banner::query()->whereHas('slot', fn ($query) => $query->where('key', 'home-partners'))
                ->where('is_active', true)->get(),
            'bannerTop' => $this->banners->forSlot('home-top', $bannerContext),
            'bannerMid' => $this->banners->forSlot('home-mid', $bannerContext),
            'bannerBottom' => $this->banners->forSlot('home-bottom', $bannerContext),
        ]);
    }

    /**
     * No country is selected until a visitor picks one, and the home page
     * still has to resolve to *something* on a first visit — the first
     * active country in registry order, the same tie-break the switcher
     * itself already lists in.
     */
    private function resolveCountry(): ?Country
    {
        $code = session('public.country');

        if (is_string($code)) {
            $selected = Country::query()->where('code', $code)->where('is_active', true)->first();

            if ($selected instanceof Country) {
                return $selected;
            }
        }

        return Country::query()->where('is_active', true)->orderBy('display_order')->first();
    }

    /**
     * There is no single "country root" territory record — a country is a
     * dimension of its own, not one node in the territory tree — so the
     * closest real stand-in for "this country's page" is its own top-level
     * territory with the lowest display order, the same reading the country
     * switcher's own landing-page redirect already uses.
     */
    private function resolveTopLevelTerritory(?Country $country): ?Territory
    {
        if (! $country instanceof Country) {
            return null;
        }

        return Territory::query()
            ->where('country_id', $country->id)
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('display_order')
            ->first();
    }

    /** @return Collection<int, Object_> */
    private function objectsRail(?Country $country, ?Carbon $createdAfter = null): Collection
    {
        if (! $country instanceof Country) {
            return collect();
        }

        $criteria = new CatalogSearchCriteria(
            countryId: $country->id,
            createdAfter: $createdAfter,
            perPage: self::OBJECTS_PER_RAIL,
        );

        return $this->catalog->search($criteria)->getCollection();
    }

    /**
     * Each top-level category tile's own count includes its children's
     * objects too — a visitor comparing "Accommodation: 42" against
     * "Restaurants: 7" is comparing the grouped registry, not one leaf type.
     *
     * @return list<array{entry: PublicNavigationEntry, count: int}>
     */
    private function categoryTiles(?Country $country): array
    {
        if (! $country instanceof Country) {
            return [];
        }

        $counts = Object_::query()
            ->where('status', 'published')
            ->where('country_id', $country->id)
            ->selectRaw('object_type_id, count(*) as aggregate')
            ->groupBy('object_type_id')
            ->pluck('aggregate', 'object_type_id');

        $tiles = [];

        foreach ($this->shell->navigationGroups() as $group) {
            $count = (int) ($counts[$group->id] ?? 0);

            foreach ($group->children as $child) {
                $count += (int) ($counts[$child->id] ?? 0);
            }

            if ($count > 0) {
                $tiles[] = ['entry' => $group, 'count' => $count];
            }
        }

        return $tiles;
    }

    /** @return Collection<int, Promotion> */
    private function promotions(?Country $country): Collection
    {
        if (! $country instanceof Country) {
            return collect();
        }

        return Promotion::published()
            ->whereHas('territory', fn ($query) => $query->where('country_id', $country->id))
            // 'media' eager-loaded so the card's cover image
            // (getFirstMediaUrl) never re-queries per row.
            ->with(['translations', 'media'])
            ->limit(self::PROMOTIONS_PER_RAIL)
            ->get();
    }

    /** @return Collection<int, NewsItem> */
    private function newsItems(?Country $country): Collection
    {
        if (! $country instanceof Country) {
            return collect();
        }

        return NewsItem::published()
            ->where(function ($query) use ($country): void {
                $query->whereNull('territory_id')
                    ->orWhereHas('territory', fn ($territoryQuery) => $territoryQuery->where('country_id', $country->id));
            })
            // 'media' eager-loaded so the card's cover image
            // (getFirstMediaUrl) never re-queries per row.
            ->with(['translations', 'media'])
            ->orderByDesc('is_pinned')
            ->latest('publish_at')
            ->limit(self::NEWS_PER_RAIL)
            ->get();
    }

    /** @return Collection<int, Review> */
    private function reviews(?Country $country): Collection
    {
        if (! $country instanceof Country) {
            return collect();
        }

        return Review::query()
            ->where('status', 'published')
            ->whereHas('object', fn ($query) => $query->where('status', 'published')->where('country_id', $country->id))
            ->with('object.translations')
            ->latest()
            ->limit(self::REVIEWS_PER_RAIL)
            ->get();
    }
}
