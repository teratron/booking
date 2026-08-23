<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Object_;
use App\Models\Promotion;
use App\Models\Territory;
use App\Services\Seo\MetadataResolver;
use App\Services\Seo\PublicSlugResolver;
use App\Services\Seo\PublicUrlGenerator;
use App\Services\Seo\StructuredDataBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * A single promotion's own detail page. Promotions are always scoped to
 * exactly one object and one territory (never portal-wide), so there is no
 * standalone promotions listing to pair this with — a promotion is always
 * reached from the object or territory page that already lists it.
 */
final class PromotionController extends Controller
{
    public function __construct(
        private readonly PublicUrlGenerator $urls,
        private readonly MetadataResolver $metadata,
        private readonly StructuredDataBuilder $structuredData,
        private readonly PublicSlugResolver $resolver,
    ) {}

    /**
     * `$slug` binds by translated slug, not the raw primary key — a
     * non-existent slug 404s cleanly instead of reaching Postgres as an
     * invalid `bigint` comparison. A numeric segment matching a real,
     * publicly visible promotion's own id redirects permanently to its
     * canonical slug URL, so a link built before slug addressing existed
     * keeps working.
     */
    public function __invoke(string $lang, string $slug): View|RedirectResponse
    {
        $promotion = $this->resolver->resolvePromotionSlug($lang, $slug);

        if (! $promotion instanceof Promotion) {
            if (ctype_digit($slug)) {
                $byId = Promotion::query()->with('translations')->find((int) $slug);

                if ($byId instanceof Promotion && $this->isPubliclyVisible($byId) && $byId->slug !== null) {
                    return redirect()->route('public.promotions.show', ['lang' => $lang, 'slug' => $byId->slug], 301);
                }
            }

            abort(404);
        }

        abort_unless($this->isPubliclyVisible($promotion), 404);

        $promotion->loadMissing(['translations', 'object.translations', 'territory.translations', 'territory.country']);

        $object = $promotion->object;
        $territory = $promotion->territory;

        // A promotion is always scoped to exactly one object and one
        // territory — a data-integrity guarantee this task does not own,
        // not a reachable public state.
        abort_unless($object instanceof Object_ && $territory instanceof Territory, 500);

        $objectUrl = $this->urls->objectUrl($object, $lang) ?? url()->current();
        $selfUrl = route('public.promotions.show', ['lang' => $lang, 'slug' => $promotion->slug]);

        return view('public.promotions.show', [
            'promotion' => $promotion,
            'objectUrl' => $objectUrl,
            'territoryUrl' => $this->urls->territoryUrl($territory, $lang) ?? url()->current(),
            'breadcrumbs' => [
                ['label' => (string) ($object->name ?? ''), 'url' => $objectUrl],
                ['label' => (string) ($promotion->title ?? ''), 'url' => $selfUrl],
            ],
            'metadata' => $this->metadata->resolve($promotion, $lang, $selfUrl),
            'structuredData' => $this->structuredData->forPromotion($promotion),
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
