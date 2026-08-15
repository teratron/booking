<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Object_;
use App\Models\Territory;
use App\Services\Analytics\EventCaptureService;
use App\Services\Catalog\ObjectProfilePresenter;
use App\Services\Seo\PublicSlugResolver;
use App\Services\Seo\PublicUrlGenerator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;

/**
 * The public object profile page: one type-aware composition covering
 * every section {@see ObjectProfilePresenter} resolves — breadcrumb,
 * cover/gallery, name/type/category/rating/settlement, descriptions, the
 * contact rail, the type-varying detail block, services/infrastructure,
 * and reviews. Nearby/similar objects compose into this page from their
 * own, separately built section.
 */
final class ObjectPageController extends Controller
{
    /**
     * The object page's own "cache hit" budget in practice — the
     * presenter's read model, not the event captures above it, which must
     * fire on every real visit regardless of cache state.
     */
    private const int PROFILE_CACHE_TTL_SECONDS = 300;

    public function __construct(
        private readonly ObjectProfilePresenter $presenter,
        private readonly EventCaptureService $events,
        private readonly PublicSlugResolver $resolver,
        private readonly PublicUrlGenerator $urls,
    ) {}

    public function show(string $lang, string $slug): View
    {
        $object = $this->resolver->resolveObjectSlug($lang, $slug);

        abort_unless($object instanceof Object_, 404);
        abort_unless($object->status === 'published', 404);

        $object->loadMissing(['translations', 'territory.translations', 'territory.country']);

        $this->events->capture('object_page_view', $object, [
            'territory_id' => $object->territory_id,
            'country_id' => $object->country_id,
        ]);

        $photoCount = $object->getMedia('photos')->count();

        for ($i = 0; $i < $photoCount; $i++) {
            $this->events->capture('photo_view', $object, [
                'territory_id' => $object->territory_id,
                'country_id' => $object->country_id,
            ]);
        }

        $profile = Cache::remember(
            sprintf('object:profile:%d:%s', $object->id, $lang),
            self::PROFILE_CACHE_TTL_SECONDS,
            fn () => $this->presenter->present($object)
        );

        return view('public.object.show', [
            'object' => $object,
            'profile' => $profile,
            'breadcrumbs' => $this->breadcrumbs($object),
        ]);
    }

    /** @return list<array{label: string, url: string}> */
    private function breadcrumbs(Object_ $object): array
    {
        $territory = $object->territory;

        if (! $territory instanceof Territory) {
            return [];
        }

        $chain = $territory->ancestors()->with(['translations', 'country'])->get()->reverse()->push($territory);

        $crumbs = array_values($chain->map(fn (Territory $node): array => [
            'label' => (string) ($node->name ?? ''),
            'url' => $this->urls->territoryUrl($node) ?? url()->current(),
        ])->all());

        $crumbs[] = [
            'label' => (string) ($object->name ?? ''),
            'url' => $this->urls->objectUrl($object) ?? url()->current(),
        ];

        return $crumbs;
    }
}
