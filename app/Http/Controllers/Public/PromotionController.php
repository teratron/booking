<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Jobs\PromotionArchivalJob;
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
     * Checks `ends_at` directly rather than deferring entirely to
     * {@see PromotionArchivalJob}'s own `status` transition — that
     * job runs once daily, so an elapsed promotion could otherwise still
     * serve its own page as current for up to 24 hours. This is defence in
     * depth, not a replacement: the job still owns the durable transition
     * (dropping the promotion from every listing that reads `status`), and
     * this check only additionally guards the one page that reads
     * `ends_at` directly.
     */
    private function isPubliclyVisible(Promotion $promotion): bool
    {
        return $promotion->status === 'published'
            && $promotion->starts_at->lte(now())
            && $promotion->ends_at->gte(now());
    }
}
