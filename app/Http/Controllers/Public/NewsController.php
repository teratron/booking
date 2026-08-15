<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\NewsItem;
use Illuminate\Contracts\View\View;

/**
 * The portal-wide public news feed and each item's own detail page. A news
 * item past its own end date is excluded from the feed but its own page
 * stays reachable — the feed and the page apply two deliberately different
 * visibility gates for exactly that reason.
 */
final class NewsController extends Controller
{
    private const int NEWS_PER_PAGE = 12;

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
     * `$lang` is unused but must stay declared first: Laravel's controller
     * dependency resolver splices route parameters by reflection position,
     * and a leading route segment with no corresponding method parameter
     * throws that splice out of alignment for the model that follows.
     */
    public function show(string $lang, NewsItem $newsItem): View
    {
        abort_unless($this->isPubliclyVisible($newsItem), 404);

        $newsItem->loadMissing(['translations', 'territory.translations', 'object.translations']);

        return view('public.news.show', [
            'newsItem' => $newsItem,
            'breadcrumbs' => [
                ['label' => __('public.shell.nav.news'), 'url' => route('public.news.index', ['lang' => $lang])],
                ['label' => (string) ($newsItem->title ?? ''), 'url' => route('public.news.show', ['lang' => $lang, 'newsItem' => $newsItem])],
            ],
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
