<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\NewsItem;
use App\Services\Seo\MetadataResolver;
use App\Services\Seo\PublicSlugResolver;
use App\Services\Seo\StructuredDataBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * The portal-wide public news feed and each item's own detail page. A news
 * item past its own end date is excluded from the feed but its own page
 * stays reachable — the feed and the page apply two deliberately different
 * visibility gates for exactly that reason.
 */
final class NewsController extends Controller
{
    private const int NEWS_PER_PAGE = 12;

    public function __construct(
        private readonly MetadataResolver $metadata,
        private readonly StructuredDataBuilder $structuredData,
        private readonly PublicSlugResolver $resolver,
    ) {}

    public function index(string $lang): View
    {
        $newsItems = NewsItem::published()
            ->with('translations')
            ->orderByDesc('is_pinned')
            ->latest('publish_at')
            ->paginate(self::NEWS_PER_PAGE);

        return view('public.news.index', [
            'newsItems' => $newsItems,
            'breadcrumbs' => [
                ['label' => __('public.shell.nav.news'), 'url' => route('public.news.index', ['lang' => $lang])],
            ],
        ]);
    }

    /**
     * `$slug` binds by translated slug, not the raw primary key — a
     * non-existent slug 404s cleanly instead of reaching Postgres as an
     * invalid `bigint` comparison. A numeric segment matching a real,
     * publicly visible item's own id redirects permanently to its
     * canonical slug URL, so a link built before slug addressing existed
     * keeps working.
     */
    public function show(string $lang, string $slug): View|RedirectResponse
    {
        $newsItem = $this->resolver->resolveNewsSlug($lang, $slug);

        if (! $newsItem instanceof NewsItem) {
            if (ctype_digit($slug)) {
                $byId = NewsItem::query()->with('translations')->find((int) $slug);

                if ($byId instanceof NewsItem && $this->isPubliclyVisible($byId) && $byId->slug !== null) {
                    return redirect()->route('public.news.show', ['lang' => $lang, 'slug' => $byId->slug], 301);
                }
            }

            abort(404);
        }

        abort_unless($this->isPubliclyVisible($newsItem), 404);

        $newsItem->loadMissing(['translations', 'territory.translations', 'territory.country', 'object.translations', 'author']);

        $selfUrl = route('public.news.show', ['lang' => $lang, 'slug' => $newsItem->slug]);

        return view('public.news.show', [
            'newsItem' => $newsItem,
            'breadcrumbs' => [
                ['label' => __('public.shell.nav.news'), 'url' => route('public.news.index', ['lang' => $lang])],
                ['label' => (string) ($newsItem->title ?? ''), 'url' => $selfUrl],
            ],
            'metadata' => $this->metadata->resolve($newsItem, $lang, $selfUrl),
            'structuredData' => $this->structuredData->forArticleLike(
                (string) ($newsItem->title ?? ''),
                $newsItem->author?->name,
                $newsItem->publish_at,
                $newsItem->getFirstMediaUrl('cover_image') ?: null,
            ),
        ]);
    }

    /**
     * Matches {@see NewsItem::scopePublished()} minus its own `end_at`
     * check — a news item past its own end date is dropped from every
     * feed but its own page stays reachable, per the spec's own
     * withdrawn-from-feeds-page-retained distinction. Moderation is
     * already enforced by the model's own global scope before this
     * method ever runs.
     */
    private function isPubliclyVisible(NewsItem $newsItem): bool
    {
        return $newsItem->status === 'published'
            && ($newsItem->publish_at === null || $newsItem->publish_at->lte(now()));
    }
}
