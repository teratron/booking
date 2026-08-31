<?php

declare(strict_types=1);

namespace App\Livewire\Public;

use App\Models\AmenityGroup;
use App\Models\ObjectType;
use App\Models\Territory;
use App\Services\Advertising\BannerSelectionService;
use App\Services\Catalog\AttributeFilterResolver;
use App\Services\Catalog\CatalogQueryService;
use App\Services\Seo\IndexationPolicy;
use App\Services\Seo\MetadataResolver;
use App\Services\Shell\PublicShellDataProvider;
use App\Support\Catalog\CatalogSearchCriteria;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The catalog search results page: every search parameter round-trips
 * through the URL (shareable, back-navigable), and every filter change
 * re-renders both the result list and the embedded map's pins in the same
 * Livewire round trip — the map picks up the `catalog-filters-changed`
 * browser event this component dispatches on every render.
 *
 * Ordering is never decided here — {@see CatalogQueryService::search()}
 * owns the entire tier-ordering contract; this component only assembles
 * the criteria a visitor's filter selection describes.
 */
#[Layout('components.layouts.public')]
final class CatalogSearch extends Component
{
    use WithPagination;

    #[Url]
    public string $q = '';

    #[Url]
    public ?int $type = null;

    #[Url]
    public ?int $territoryId = null;

    /** @var list<int> */
    #[Url]
    public array $amenities = [];

    #[Url]
    public ?float $priceMin = null;

    #[Url]
    public ?float $priceMax = null;

    #[Url]
    public ?float $ratingMin = null;

    /** @var array<string, mixed> */
    #[Url]
    public array $attrs = [];

    #[Url(as: 'view')]
    public string $viewMode = 'grid';

    public function updatingQ(): void
    {
        $this->resetPage();
    }

    public function updatingType(): void
    {
        $this->resetPage();
    }

    public function updatingTerritoryId(): void
    {
        $this->resetPage();
    }

    public function updatingAmenities(): void
    {
        $this->resetPage();
    }

    public function updatingPriceMin(): void
    {
        $this->resetPage();
    }

    public function updatingPriceMax(): void
    {
        $this->resetPage();
    }

    public function updatingRatingMin(): void
    {
        $this->resetPage();
    }

    public function updatingAttrs(): void
    {
        $this->resetPage();
    }

    public function setViewMode(string $mode): void
    {
        $this->viewMode = $mode === 'list' ? 'list' : 'grid';
    }

    public function render(): View
    {
        // Resolved once and reused both for the criteria's own ordering-scope
        // hint and the amenity-group facet lookup below — two independent
        // ObjectType::find() calls for the same id on every render is
        // exactly the kind of per-request duplication a real query budget
        // catches that a dozen-fixture test never would.
        $selectedType = $this->type !== null ? ObjectType::query()->with('translations')->find($this->type) : null;

        $criteria = new CatalogSearchCriteria(
            territory: $this->territoryId !== null ? Territory::query()->find($this->territoryId) : null,
            objectTypeId: $this->type,
            objectType: $selectedType,
            name: $this->q !== '' ? $this->q : null,
            amenityIds: $this->amenities,
            priceMin: $this->priceMin,
            priceMax: $this->priceMax,
            ratingMin: $this->ratingMin,
            attributeFilters: $this->activeAttributeFilters($selectedType),
            page: $this->getPage(),
        );

        $results = app(CatalogQueryService::class)->search($criteria);

        // Same round trip: this fires as part of the response this render()
        // call produces, so the map's pins refresh alongside the results
        // list rather than through a second, independent request.
        $this->dispatch(
            'catalog-filters-changed',
            type: $this->type,
            q: $this->q !== '' ? $this->q : null,
        );

        $indexable = app(IndexationPolicy::class)->catalogIndexable($this->activeIndexationFilters($selectedType), $this->q !== '');
        // url()->full() reads the request's own Host/scheme directly,
        // bypassing the app-wide URL::forceRootUrl()/forceScheme() pin —
        // url()->current() goes through the same UrlGenerator::to() path
        // route()/url('path') already do, so the query string (which this
        // page's own canonical must keep, unlike a plain page URL) is
        // appended manually rather than through url()->full()'s shortcut.
        $selfUrl = url()->current();
        $queryString = request()->getQueryString();
        $metadata = app(MetadataResolver::class)->resolveCatalog($selectedType, $criteria->territory, $indexable, app()->getLocale(), $queryString !== null ? "{$selfUrl}?{$queryString}" : $selfUrl);

        return view('livewire.public.catalog-search', [
            'results' => $results,
            'selectedType' => $selectedType,
            'typeGroups' => app(PublicShellDataProvider::class)->navigationGroups(),
            'amenityGroups' => $this->filterableAmenityGroups($selectedType),
            // Top-level (region) territories only, plus whichever one is
            // currently selected so its label always resolves. The full
            // registry is 6,000+ rows and grows with a runtime-extensible
            // dimension — inlining all of it into one <select> is a
            // response-size budget failure ([TZ] §18) and unusable as a
            // control. Finer-grained filtering is what the territory
            // landing pages themselves are for.
            'territories' => Territory::query()
                ->where('is_active', true)
                ->where(fn ($query) => $query
                    ->whereNull('parent_id')
                    ->when($this->territoryId !== null, fn ($q) => $q->orWhere('id', $this->territoryId)))
                ->orderBy('display_order')
                ->with('translations')
                ->get(),
            'bannerTop' => app(BannerSelectionService::class)->forSlot('catalog-top', [
                'territory' => $criteria->territory,
                'category' => $selectedType,
                'language' => app()->getLocale(),
            ]),
        ])->layoutData(['metadata' => $metadata]);
    }

    /**
     * The active filter dimensions {@see IndexationPolicy} decides
     * indexability from — one entry per dimension regardless of how many
     * discrete values it carries (e.g. several amenities still count as
     * the single `amenity` dimension), since a promotion is a decision
     * about one dimension's value, never about how many values within it.
     *
     * @return array<string, scalar>
     */
    private function activeIndexationFilters(?ObjectType $type): array
    {
        $filters = [];

        if ($this->type !== null) {
            $filters['type'] = $this->type;
        }

        if ($this->territoryId !== null) {
            $filters['territory'] = $this->territoryId;
        }

        if ($this->priceMin !== null || $this->priceMax !== null) {
            $filters['price'] = sprintf('%s-%s', $this->priceMin ?? '', $this->priceMax ?? '');
        }

        if ($this->ratingMin !== null) {
            $filters['rating'] = $this->ratingMin;
        }

        if ($this->amenities !== []) {
            $filters['amenity'] = implode(',', $this->amenities);
        }

        $attributes = $this->activeAttributeFilters($type);

        if ($attributes !== []) {
            $filters['attribute'] = (string) json_encode($attributes);
        }

        return $filters;
    }

    /**
     * The visitor's raw `attrs` selection resolved against the selected
     * type's own declared schema by {@see AttributeFilterResolver} —
     * `attrs` round-trips through the URL, so its keys and values arrive
     * entirely unconstrained and must be reconciled with what the type
     * actually declares before they become query filters.
     *
     * @return array<string, array{min?: float, max?: float}|scalar>
     */
    private function activeAttributeFilters(?ObjectType $type): array
    {
        return app(AttributeFilterResolver::class)->resolve($type, $this->attrs);
    }

    /**
     * With no type chosen yet, filtering by service is still required —
     * Figma's own sidebar is permanently visible, not something
     * that appears after a type decision. The union of every active type's
     * amenity groups is the honest answer to "what can I filter by right
     * now": once a type is picked, {@see ObjectType::amenityGroups()} narrows
     * this back down to that type's own groups, same as before.
     *
     * @return Collection<int, AmenityGroup>
     */
    private function filterableAmenityGroups(?ObjectType $type): Collection
    {
        $groups = $type instanceof ObjectType
            ? $type->amenityGroups()
            : AmenityGroup::query()->whereHas(
                'objectTypes',
                fn ($objectTypes) => $objectTypes->where('object_types.is_active', true),
            );

        return $groups
            ->where('is_active', true)
            ->with(['translations', 'amenities' => function ($query): void {
                $query->where('is_filterable', true)->where('is_active', true)->with('translations');
            }])
            ->get()
            ->filter(fn (AmenityGroup $group): bool => $group->amenities->isNotEmpty())
            ->unique('id')
            ->values();
    }
}
