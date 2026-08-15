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
use App\Support\Catalog\CatalogSearchCriteria;
use Illuminate\Contracts\View\View;
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
 */
final class TerritoryPageController extends Controller
{
    private const int OBJECTS_PER_BLOCK = 6;

    private const int NEWS_PER_BLOCK = 6;

    private const int PROMOTIONS_PER_BLOCK = 6;

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
    ) {}

    public function show(string $lang, Territory $territory): View
    {
        abort_unless($territory->is_active, 404);

        $territory->loadMissing('translations');

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
                    ->with('translations')
                    ->get(),
            ]
        );

        return view('public.territory.show', array_merge($sidebar, [
            'territory' => $territory,
            'breadcrumbs' => $this->breadcrumbs($territory),
            'catalogBlocks' => $this->catalogBlocks($territory),
        ]));
    }

    /** @return list<array{label: string, url: string}> */
    private function breadcrumbs(Territory $territory): array
    {
        $chain = $territory->ancestors()->with('translations')->get()->reverse()->push($territory);

        return array_values($chain->map(fn (Territory $node): array => [
            'label' => (string) ($node->name ?? ''),
            'url' => route('public.territories.show', ['lang' => app()->getLocale(), 'territory' => $node->id]),
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
}
