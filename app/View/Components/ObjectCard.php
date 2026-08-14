<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Models\Object_;
use App\Services\Analytics\EventCaptureService;
use App\Services\Catalog\ObjectCardPresenter;
use App\Support\Catalog\ObjectCardViewModel;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * The single result-card component every public listing surface renders —
 * home, catalog, territory pages, an object's own nearby/similar block.
 * Composition and data resolution live in {@see ObjectCardPresenter}; this
 * class is the thin Blade-facing wrapper plus the card's own view-emission
 * point, since a view is captured once per rendered card, not once per
 * page.
 */
final class ObjectCard extends Component
{
    public ObjectCardViewModel $card;

    public function __construct(
        Object_ $object,
        ObjectCardPresenter $presenter,
        EventCaptureService $events,
    ) {
        $this->card = $presenter->present($object);

        $events->capture('object_card_view', $object, [
            'territory_id' => $object->territory_id,
            'country_id' => $object->country_id,
        ]);
    }

    public function render(): View
    {
        return view('components.object-card');
    }
}
