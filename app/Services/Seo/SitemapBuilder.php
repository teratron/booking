<?php

declare(strict_types=1);

namespace App\Services\Seo;

use App\Models\Language;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\SitemapIndex;
use Spatie\Sitemap\Tags\Url;

/**
 * Writes the sitemap index and its per-language, per-entity-type children
 * as static XML artefacts — read back unchanged by the routes that serve
 * them, never computed per request. Only indexable pages appear: every
 * entity query applies the same publication and `seo_indexable` rules the
 * public pages themselves render under, mirrored here as plain query
 * builder conditions rather than hydrating Eloquent models for what can be
 * thousands of rows per language.
 *
 * Deliberately excludes the typed-catalog page (`{settlement}/{type}`):
 * it is a territory-and-type composite, not a persisted entity with its
 * own identity to enumerate, and it stays reachable through the territory
 * sitemap entry and the portal's own internal links either way.
 */
final class SitemapBuilder
{
    private const string STORAGE_PREFIX = 'sitemaps';

    public function generate(): void
    {
        $disk = Storage::disk((string) config('sitemap.disk'));
        $perFile = (int) config('sitemap.urls_per_file');
        $locales = Language::query()->where('is_active', true)->pluck('code');
        $childPaths = [];

        foreach ($locales as $locale) {
            foreach ($this->entityQueries() as $key => $query) {
                $rows = $query($locale);

                foreach ($rows->chunk($perFile)->values() as $index => $chunk) {
                    $path = self::STORAGE_PREFIX."/{$locale}/{$key}-".($index + 1).'.xml';
                    $disk->put($path, $this->renderSitemap($chunk));
                    $childPaths[] = $path;
                }
            }
        }

        $disk->put(self::STORAGE_PREFIX.'/sitemap.xml', $this->renderIndex($childPaths));
    }

    /**
     * @return array<string, callable(string): Collection<int, array{url: string, lastmod: ?string}>>
     */
    private function entityQueries(): array
    {
        return [
            'territory' => $this->territoryUrls(...),
            'object' => $this->objectUrls(...),
            'news' => $this->newsUrls(...),
            'article' => $this->articleUrls(...),
            'promotion' => $this->promotionUrls(...),
        ];
    }

    /** @return Collection<int, array{url: string, lastmod: ?string}> */
    private function territoryUrls(string $locale): Collection
    {
        return DB::table('territory_translations as tt')
            ->join('territories as t', 't.id', '=', 'tt.territory_id')
            ->join('countries as c', 'c.id', '=', 't.country_id')
            ->where('tt.locale', $locale)
            ->where('t.is_active', true)
            ->where('c.is_active', true)
            ->where(fn ($query) => $query->whereNull('tt.seo_indexable')->orWhere('tt.seo_indexable', true))
            ->select(['tt.full_slug_path', 'c.code as country_code', 'tt.updated_at'])
            ->orderBy('t.id')
            ->get()
            ->map(fn (object $row): array => [
                'url' => url('/'.$locale.'/'.Str::lower((string) $row->country_code).'/'.$row->full_slug_path),
                'lastmod' => $row->updated_at !== null ? (string) $row->updated_at : null,
            ]);
    }

    /** @return Collection<int, array{url: string, lastmod: ?string}> */
    private function objectUrls(string $locale): Collection
    {
        return DB::table('object_translations as ot')
            ->join('objects as o', 'o.id', '=', 'ot.object_id')
            ->where('ot.locale', $locale)
            ->where('o.status', 'published')
            ->where('o.moderation_status', 'approved')
            ->whereNull('o.deleted_at')
            ->where(fn ($query) => $query->whereNull('ot.seo_indexable')->orWhere('ot.seo_indexable', true))
            ->select(['ot.slug', 'ot.updated_at'])
            ->orderBy('o.id')
            ->get()
            ->map(fn (object $row): array => [
                'url' => url("/{$locale}/o/{$row->slug}"),
                'lastmod' => $row->updated_at !== null ? (string) $row->updated_at : null,
            ]);
    }

    /** @return Collection<int, array{url: string, lastmod: ?string}> */
    private function newsUrls(string $locale): Collection
    {
        return DB::table('news_translations as nt')
            ->join('news_items as n', 'n.id', '=', 'nt.news_item_id')
            ->where('nt.locale', $locale)
            ->where('n.status', 'published')
            ->where('n.moderation_status', 'approved')
            ->whereNull('n.deleted_at')
            ->where(fn ($query) => $query->whereNull('n.publish_at')->orWhere('n.publish_at', '<=', now()))
            ->where(fn ($query) => $query->whereNull('n.end_at')->orWhere('n.end_at', '>', now()))
            ->where(fn ($query) => $query->whereNull('nt.seo_indexable')->orWhere('nt.seo_indexable', true))
            ->select(['n.id', 'nt.slug', 'nt.updated_at'])
            ->orderBy('n.id')
            ->get()
            ->map(fn (object $row): array => [
                'url' => url("/{$locale}/news/{$row->slug}"),
                'lastmod' => $row->updated_at !== null ? (string) $row->updated_at : null,
            ]);
    }

    /** @return Collection<int, array{url: string, lastmod: ?string}> */
    private function articleUrls(string $locale): Collection
    {
        return DB::table('article_translations as at')
            ->join('articles as a', 'a.id', '=', 'at.article_id')
            ->where('at.locale', $locale)
            ->where('a.status', 'published')
            ->whereNull('a.deleted_at')
            ->where(fn ($query) => $query->whereNull('a.publish_at')->orWhere('a.publish_at', '<=', now()))
            ->where(fn ($query) => $query->whereNull('at.seo_indexable')->orWhere('at.seo_indexable', true))
            ->select(['a.id', 'at.slug', 'at.updated_at'])
            ->orderBy('a.id')
            ->get()
            ->map(fn (object $row): array => [
                'url' => url("/{$locale}/blog/{$row->slug}"),
                'lastmod' => $row->updated_at !== null ? (string) $row->updated_at : null,
            ]);
    }

    /** @return Collection<int, array{url: string, lastmod: ?string}> */
    private function promotionUrls(string $locale): Collection
    {
        return DB::table('promotion_translations as pt')
            ->join('promotions as p', 'p.id', '=', 'pt.promotion_id')
            ->where('pt.locale', $locale)
            ->where('p.status', 'published')
            ->where('p.moderation_status', 'approved')
            ->whereNull('p.deleted_at')
            ->where('p.starts_at', '<=', now()->toDateString())
            ->where('p.ends_at', '>=', now()->toDateString())
            ->where(fn ($query) => $query->whereNull('pt.seo_indexable')->orWhere('pt.seo_indexable', true))
            ->select(['p.id', 'pt.slug', 'pt.updated_at'])
            ->orderBy('p.id')
            ->get()
            ->map(fn (object $row): array => [
                'url' => url("/{$locale}/promotions/{$row->slug}"),
                'lastmod' => $row->updated_at !== null ? (string) $row->updated_at : null,
            ]);
    }

    /** @param  Collection<int, array{url: string, lastmod: ?string}>  $rows */
    private function renderSitemap(Collection $rows): string
    {
        $sitemap = Sitemap::create();

        foreach ($rows as $row) {
            $tag = Url::create($row['url']);

            if ($row['lastmod'] !== null) {
                $tag->setLastModificationDate(Carbon::parse($row['lastmod']));
            }

            $sitemap->add($tag);
        }

        return $sitemap->render();
    }

    /** @param  list<string>  $childPaths */
    private function renderIndex(array $childPaths): string
    {
        $index = SitemapIndex::create();

        foreach ($childPaths as $path) {
            $index->add(route('public.sitemaps.child', [
                'locale' => explode('/', $path)[1],
                'filename' => basename($path),
            ]));
        }

        return $index->render();
    }
}
