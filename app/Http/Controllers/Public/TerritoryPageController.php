<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\NewsItem;
use App\Models\Object_;
use App\Models\ObjectType;
use App\Models\Promotion;
use App\Models\Territory;
use App\Services\Catalog\CatalogQueryService;
use App\Services\Seo\MetadataResolver;
use App\Services\Seo\PublicSlugResolver;
use App\Services\Seo\PublicUrlGenerator;
use App\Services\Seo\StructuredDataBuilder;
use App\Support\Catalog\CatalogSearchCriteria;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * A territory's own public landing page: breadcrumb, hero, description, one
 * catalog block per active object type (each independently omitted when
 * empty), territory news and promotions, a centred map, an SEO text block,
 * and child-territory navigation. Every catalog block is a scoped call into
 * {@see CatalogQueryService::search()} — never a page-specific query — so
 * tier ordering and transitive descendant scoping come from that one
 * contract rather than being re-derived here.
 *
 * The same action also serves the typed-catalog-within-a-territory page
 * (`{settlement}/{type}`) — {@see PublicSlugResolver} disambiguates the two
 * shapes from one `{path}` segment, since both belong to the same territory
 * addressing space and a second controller would duplicate the resolution.
 */
final class TerritoryPageController extends Controller
{
    private const int OBJECTS_PER_BLOCK = 6;

    private const int NEWS_PER_BLOCK = 6;

    private const int PROMOTIONS_PER_BLOCK = 6;

    private const int TYPED_CATALOG_PER_PAGE = 24;

    /**
     * The territory page's own "cache hit" budget in practice — the
     * news/promotions/child-territory reads below are cached as a unit;
     * `catalogBlocks()` needs no cache of its own here, since every block it
     * builds is already a {@see CatalogQueryService::search()} call, which
     * caches at that shared layer.
     */
    private const int SIDEBAR_CACHE_TTL_SECONDS = 300;

    public function __construct(
        private readonly CatalogQueryService $catalog,
        private readonly PublicSlugResolver $resolver,
        private readonly PublicUrlGenerator $urls,
        private readonly MetadataResolver $metadata,
        private readonly StructuredDataBuilder $structuredData,
    ) {}

    public function show(Request $request, string $lang, string $country, string $path): View
    {
        $resolved = $this->resolver->resolveTerritoryPath($lang, $country, $path);

        abort_unless($resolved !== null, 404);

        $territory = $resolved['territory'];
        $objectType = $resolved['objectType'];

        abort_unless($territory->is_active, 404);

        $territory->loadMissing('translations');

        if ($objectType instanceof ObjectType) {
            return $this->showTypedCatalog($request, $lang, $territory, $objectType);
        }

        $sidebar = Cache::remember(
            sprintf('territory:sidebar:%d:%s', $territory->id, $lang),
            self::SIDEBAR_CACHE_TTL_SECONDS,
            fn (): array => [
                'newsItems' => NewsItem::published()->where('territory_id', $territory->id)
                    ->with('translations')
                    ->latest('publish_at')
                    ->limit(self::NEWS_PER_BLOCK)
                    ->get(),
                'promotions' => Promotion::published()->where('territory_id', $territory->id)
                    ->with('translations')
                    ->limit(self::PROMOTIONS_PER_BLOCK)
                    ->get(),
                'childTerritories' => $territory->children()->where('is_active', true)
                    ->orderBy('display_order')
                    ->with(['translations', 'country'])
                    ->get(),
            ]
        );

        $catalogBlocks = $this->catalogBlocks($territory);

        return view('public.territory.show', array_merge($sidebar, [
            'territory' => $territory,
            'breadcrumbs' => $this->breadcrumbs($territory),
            'catalogBlocks' => $catalogBlocks,
            'metadata' => $this->metadata->resolve($territory, $lang, $this->urls->territoryUrl($territory, $lang)),
            'structuredData' => $this->structuredData->forTerritory($territory, $this->containedObjectItems($catalogBlocks)),
        ]));
    }

    /**
     * A focused, indexable single-type listing within one territory —
     * distinct from `/catalog`'s own query-string-filtered results, which
     * are not indexable by default. Deliberately a plain paginated list
     * rather than the full interactive filter set: the clean path is what
     * makes it eligible for indexing at all.
     */
    private function showTypedCatalog(Request $request, string $lang, Territory $territory, ObjectType $objectType): View
    {
        $objectType->loadMissing('translations');

        $criteria = new CatalogSearchCriteria(
            territory: $territory,
            objectTypeId: $objectType->id,
            objectType: $objectType,
            page: $request->integer('page', 1),
            perPage: self::TYPED_CATALOG_PER_PAGE,
        );

        $results = $this->catalog->search($criteria);

        $selfUrl = $this->urls->typedCatalogUrl($territory, $objectType, $lang);
        $breadcrumbs = $this->breadcrumbs($territory);
        $breadcrumbs[] = ['label' => (string) ($objectType->name ?? ''), 'url' => $selfUrl ?? url()->current()];

        return view('public.territory.typed-catalog', [
            'territory' => $territory,
            'objectType' => $objectType,
            'results' => $results,
            'breadcrumbs' => $breadcrumbs,
            'metadata' => $this->metadata->resolveTypedCatalog($territory, $objectType, $lang, $selfUrl),
        ]);
    }

    /** @return list<array{label: string, url: string}> */
    private function breadcrumbs(Territory $territory): array
    {
        $chain = $territory->ancestors()->with(['translations', 'country'])->get()->reverse()->push($territory);

        return array_values($chain->map(fn (Territory $node): array => [
            'label' => (string) ($node->name ?? ''),
            'url' => $this->urls->territoryUrl($node) ?? url()->current(),
        ])->all());
    }

    /**
     * One block per active object type with at least one matching object in
     * this territory's own subtree — a type with nothing to show here is
     * omitted entirely, never rendered empty.
     *
     * @return list<array{type: ObjectType, objects: Collection<int, Object_>}>
     */
    private function catalogBlocks(Territory $territory): array
    {
        $types = ObjectType::query()->where('is_active', true)->orderBy('display_order')->with('translations')->get();

        $blocks = [];

        foreach ($types as $type) {
            $criteria = new CatalogSearchCriteria(
                territory: $territory,
                objectTypeId: $type->id,
                perPage: self::OBJECTS_PER_BLOCK,
            );

            $objects = $this->catalog->search($criteria)->getCollection();

            if ($objects->isEmpty()) {
                continue;
            }

            $blocks[] = ['type' => $type, 'objects' => $objects];
        }

        return $blocks;
    }

    /**
     * Flattens every already-fetched catalog block into the plain
     * name/url pairs {@see StructuredDataBuilder::forTerritory()}'s own
     * `ItemList` needs — no query of its own, since `catalogBlocks()` has
     * already fetched every object this page renders.
     *
     * @param  list<array{type: ObjectType, objects: Collection<int, Object_>}>  $catalogBlocks
     * @return Collection<int, array{name: string, url: string}>
     */
    private function containedObjectItems(array $catalogBlocks): Collection
    {
        return collect($catalogBlocks)
            ->flatMap(fn (array $block): Collection => $block['objects'])
            ->map(fn (Object_ $object): array => [
                'name' => (string) ($object->name ?? ''),
                'url' => $this->urls->objectUrl($object) ?? url()->current(),
            ])
            ->values();
    }
}
