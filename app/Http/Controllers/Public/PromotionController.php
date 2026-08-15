<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use Illuminate\Contracts\View\View;

/**
 * A single promotion's own detail page. Promotions are always scoped to
 * exactly one object and one territory (never portal-wide), so there is no
 * standalone promotions listing to pair this with — a promotion is always
 * reached from the object or territory page that already lists it.
 */
final class PromotionController extends Controller
{
    /**
     * `$lang` is unused but must stay declared first: Laravel's controller
     * dependency resolver splices route parameters by reflection position,
     * and a leading route segment with no corresponding method parameter
     * throws that splice out of alignment for the model that follows.
     */
    public function __invoke(string $lang, Promotion $promotion): View
    {
        abort_unless($this->isPubliclyVisible($promotion), 404);

        $promotion->loadMissing(['translations', 'object.translations', 'territory.translations']);

        return view('public.promotions.show', [
            'promotion' => $promotion,
            'breadcrumbs' => [
                ['label' => (string) ($promotion->object->name ?? ''), 'url' => route('public.objects.show', ['lang' => $lang, 'object' => $promotion->object])],
                ['label' => (string) ($promotion->title ?? ''), 'url' => route('public.promotions.show', ['lang' => $lang, 'promotion' => $promotion])],
            ],
        ]);
    }

    /**
     * Matches {@see Promotion::scopePublished()} minus its own `ends_at`
     * check — the scheduled archival job this task does not own already
     * transitions an elapsed promotion's own `status` away from
     * `published` (unlike a news item's `end_at`, which never changes
     * `status`), so `status` alone is not enough on its own; `starts_at`
     * still guards against a promotion published administratively ahead
     * of its own start date.
     */
    private function isPubliclyVisible(Promotion $promotion): bool
    {
        return $promotion->status === 'published'
            && $promotion->starts_at->lte(now());
    }
}
