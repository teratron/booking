<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Services\Seo\MetadataResolver;
use Illuminate\Contracts\View\View;

/**
 * The public blog: a paginated listing of published articles and each
 * article's own detail page — full body, author, category, tags, and its
 * many-to-many related objects and territories, each a real link into
 * that object's or territory's own public page.
 */
final class BlogController extends Controller
{
    private const int ARTICLES_PER_PAGE = 12;

    public function __construct(private readonly MetadataResolver $metadata) {}

    public function index(string $lang): View
    {
        $articles = Article::published()
            ->with('translations', 'category.translations', 'tags')
            ->latest('publish_at')
            ->paginate(self::ARTICLES_PER_PAGE);

        return view('public.blog.index', [
            'articles' => $articles,
            'breadcrumbs' => [
                ['label' => __('public.shell.nav.blog'), 'url' => route('public.blog.index', ['lang' => $lang])],
            ],
        ]);
    }

    /**
     * `$lang` is unused but must stay declared first: Laravel's controller
     * dependency resolver splices route parameters by reflection position,
     * and a leading route segment with no corresponding method parameter
     * throws that splice out of alignment for the model that follows.
     */
    public function show(string $lang, Article $article): View
    {
        abort_unless($this->isPubliclyVisible($article), 404);

        $article->loadMissing([
            'translations', 'category.translations', 'tags',
            'objects.translations', 'territories.translations', 'territories.country',
        ]);

        $selfUrl = route('public.blog.show', ['lang' => $lang, 'article' => $article]);

        return view('public.blog.show', [
            'article' => $article,
            'breadcrumbs' => [
                ['label' => __('public.shell.nav.blog'), 'url' => route('public.blog.index', ['lang' => $lang])],
                ['label' => (string) ($article->title ?? ''), 'url' => $selfUrl],
            ],
            'metadata' => $this->metadata->resolve($article, $lang, $selfUrl),
        ]);
    }

    /**
     * Matches {@see Article::scopePublished()} exactly, applied to a
     * single already-fetched model rather than a query — a future-dated
     * or draft article's own route must be as unreachable as its listing
     * row already is.
     */
    private function isPubliclyVisible(Article $article): bool
    {
        return $article->status === 'published'
            && ($article->publish_at === null || $article->publish_at->lte(now()));
    }
}
