<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Services\Seo\MetadataResolver;
use App\Services\Seo\PublicSlugResolver;
use App\Services\Seo\StructuredDataBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * The public blog: a paginated listing of published articles and each
 * article's own detail page — full body, author, category, tags, and its
 * many-to-many related objects and territories, each a real link into
 * that object's or territory's own public page.
 */
final class BlogController extends Controller
{
    private const int ARTICLES_PER_PAGE = 12;

    public function __construct(
        private readonly MetadataResolver $metadata,
        private readonly StructuredDataBuilder $structuredData,
        private readonly PublicSlugResolver $resolver,
    ) {}

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
     * `$slug` binds by translated slug, not the raw primary key — a
     * non-existent slug 404s cleanly instead of reaching Postgres as an
     * invalid `bigint` comparison. A numeric segment matching a real,
     * publicly visible article's own id redirects permanently to its
     * canonical slug URL, so a link built before slug addressing existed
     * keeps working.
     */
    public function show(string $lang, string $slug): View|RedirectResponse
    {
        $article = $this->resolver->resolveArticleSlug($lang, $slug);

        if (! $article instanceof Article) {
            if (ctype_digit($slug)) {
                $byId = Article::query()->with('translations')->find((int) $slug);

                if ($byId instanceof Article && $this->isPubliclyVisible($byId) && $byId->slug !== null) {
                    return redirect()->route('public.blog.show', ['lang' => $lang, 'slug' => $byId->slug], 301);
                }
            }

            abort(404);
        }

        abort_unless($this->isPubliclyVisible($article), 404);

        $article->loadMissing([
            'translations', 'category.translations', 'tags', 'author',
            'objects.translations', 'territories.translations', 'territories.country',
        ]);

        $selfUrl = route('public.blog.show', ['lang' => $lang, 'slug' => $article->slug]);

        return view('public.blog.show', [
            'article' => $article,
            'breadcrumbs' => [
                ['label' => __('public.shell.nav.blog'), 'url' => route('public.blog.index', ['lang' => $lang])],
                ['label' => (string) ($article->title ?? ''), 'url' => $selfUrl],
            ],
            'metadata' => $this->metadata->resolve($article, $lang, $selfUrl),
            'structuredData' => $this->structuredData->forArticleLike(
                (string) ($article->title ?? ''),
                $article->author?->name,
                $article->publish_at,
                $article->getFirstMediaUrl('cover_image') ?: null,
            ),
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
