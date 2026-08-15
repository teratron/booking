<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Object_;
use App\Models\Territory;
use App\Services\Analytics\EventCaptureService;
use App\Services\Catalog\ObjectProfilePresenter;
use Illuminate\Contracts\View\View;

/**
 * The public object profile page: one type-aware composition covering
 * every section {@see ObjectProfilePresenter} resolves — breadcrumb,
 * cover/gallery, name/type/category/rating/settlement, descriptions, the
 * type-varying detail block, and services/infrastructure. Contact rail,
 * reviews, and nearby/similar objects compose into this page from their
 * own, separately built sections.
 */
final class ObjectPageController extends Controller
{
    public function __construct(
        private readonly ObjectProfilePresenter $presenter,
        private readonly EventCaptureService $events,
    ) {}

    /**
     * `$lang` is unused but must stay declared first: Laravel's controller
     * dependency resolver splices container-resolved parameters into the
     * route's own parameter list by reflection position, and a leading
     * route segment with no corresponding method parameter throws that
     * splice out of alignment, silently misassigning `$object`.
     */
    public function show(string $lang, Object_ $object): View
    {
        abort_unless($object->status === 'published', 404);

        $object->loadMissing(['translations', 'territory.translations']);

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

        return view('public.object.show', [
            'object' => $object,
            'profile' => $this->presenter->present($object),
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

        $chain = $territory->ancestors()->with('translations')->get()->reverse()->push($territory);

        $crumbs = array_values($chain->map(fn ($node): array => [
            'label' => (string) ($node->name ?? ''),
            'url' => route('public.territories.show', ['lang' => app()->getLocale(), 'territory' => $node->id]),
        ])->all());

        $crumbs[] = [
            'label' => (string) ($object->name ?? ''),
            'url' => route('public.objects.show', ['lang' => app()->getLocale(), 'object' => $object]),
        ];

        return $crumbs;
    }
}
