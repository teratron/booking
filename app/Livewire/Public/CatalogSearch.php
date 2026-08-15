<?php

declare(strict_types=1);

namespace App\Livewire\Public;

use App\Models\AmenityGroup;
use App\Models\ObjectType;
use App\Models\Territory;
use App\Services\Catalog\CatalogQueryService;
use App\Services\Shell\PublicShellDataProvider;
use App\Support\Catalog\CatalogSearchCriteria;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The catalog search results page: every §5.1 search parameter round-trips
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
            attributeFilters: $this->activeAttributeFilters(),
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

        return view('livewire.public.catalog-search', [
            'results' => $results,
            'selectedType' => $selectedType,
            'typeGroups' => app(PublicShellDataProvider::class)->navigationGroups(),
            'amenityGroups' => $this->filterableAmenityGroups($selectedType),
            'territories' => Territory::query()->where('is_active', true)
                ->orderBy('display_order')
                ->with('translations')
                ->get(),
        ]);
    }

    /** @return array<string, array{min?: float, max?: float}|scalar> */
    private function activeAttributeFilters(): array
    {
        $filters = [];

        foreach ($this->attrs as $key => $value) {
            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            $filters[$key] = $value;
        }

        return $filters;
    }

    /** @return Collection<int, AmenityGroup> */
    private function filterableAmenityGroups(?ObjectType $type): Collection
    {
        if (! $type instanceof ObjectType) {
            return collect();
        }

        return $type->amenityGroups()
            ->where('is_active', true)
            ->with(['translations', 'amenities' => function ($query): void {
                $query->where('is_filterable', true)->where('is_active', true)->with('translations');
            }])
            ->get()
            ->filter(fn (AmenityGroup $group): bool => $group->amenities->isNotEmpty())
            ->values();
    }
}
