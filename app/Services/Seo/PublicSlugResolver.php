<?php

declare(strict_types=1);

namespace App\Services\Seo;

use App\Models\Article;
use App\Models\ArticleTranslation;
use App\Models\Country;
use App\Models\NewsItem;
use App\Models\NewsTranslation;
use App\Models\Object_;
use App\Models\ObjectTranslation;
use App\Models\ObjectType;
use App\Models\ObjectTypeTranslation;
use App\Models\Promotion;
use App\Models\PromotionTranslation;
use App\Models\Territory;
use App\Models\TerritoryTranslation;
use Illuminate\Support\Str;

/**
 * The inbound half of the URL grammar: turns a request's `{country}` and
 * `{path}` (or a flat object slug) back into the records they address, via
 * a single indexed lookup on locale and slug — never a recursive tree walk
 * per request. {@see PublicUrlGenerator} is the same contract's outbound
 * half; the two agree on what a segment means because both are the only
 * place that decides it.
 *
 * Resolution never checks visibility (`is_active`, moderation state,
 * publication) — that stays the calling controller's decision, exactly as
 * it already was for the id-addressed routes this replaces. A miss here
 * means "no address matches," not "matches but is hidden."
 */
final class PublicSlugResolver
{
    /**
     * Resolves a `{country}/{path}` pair to the territory it addresses and,
     * when the trailing segment is a registered object-type slug in
     * `$locale`, the typed-catalog object type alongside it. Guarantees the
     * returned territory's own country matches `$countryCode` case-
     * insensitively. Returns `null` — never throws — on any miss: an empty
     * path, no country matching `$countryCode`, or no translation matching
     * `$path` in `$locale`. Assumes `$path` has no leading/trailing slash
     * requirement — both are trimmed before lookup.
     *
     * @return ?array{territory: Territory, objectType: ?ObjectType}
     */
    public function resolveTerritoryPath(string $locale, string $countryCode, string $path): ?array
    {
        $path = trim($path, '/');

        if ($path === '') {
            return null;
        }

        $direct = $this->matchTerritory($locale, $countryCode, $path);

        if ($direct instanceof Territory) {
            return ['territory' => $direct, 'objectType' => null];
        }

        // No exact match — try the trailing segment as a typed-catalog
        // object-type slug, with the remainder as the territory's own path.
        $lastSlash = strrpos($path, '/');

        if ($lastSlash === false) {
            return null;
        }

        $territoryPath = substr($path, 0, $lastSlash);
        $typeSlug = substr($path, $lastSlash + 1);

        $territory = $this->matchTerritory($locale, $countryCode, $territoryPath);

        if (! $territory instanceof Territory) {
            return null;
        }

        $typeTranslation = ObjectTypeTranslation::query()
            ->where('locale', $locale)
            ->where('slug', $typeSlug)
            ->first();

        if (! $typeTranslation instanceof ObjectTypeTranslation) {
            return null;
        }

        $objectType = ObjectType::query()->find($typeTranslation->object_type_id);

        if (! $objectType instanceof ObjectType) {
            return null;
        }

        return ['territory' => $territory, 'objectType' => $objectType];
    }

    /**
     * Resolves a flat object slug to the object it addresses, via the
     * single indexed `(locale, slug)` lookup on `object_translations`.
     * Returns `null` — never throws — when no translation matches; carries
     * no opinion on whether the resolved object is publicly visible.
     */
    public function resolveObjectSlug(string $locale, string $slug): ?Object_
    {
        $translation = ObjectTranslation::query()
            ->where('locale', $locale)
            ->where('slug', $slug)
            ->first();

        if (! $translation instanceof ObjectTranslation) {
            return null;
        }

        return Object_::query()->find($translation->object_id);
    }

    /**
     * Returns `null` — never throws — when no translation matches; carries
     * no opinion on whether the resolved item is publicly visible.
     */
    public function resolveNewsSlug(string $locale, string $slug): ?NewsItem
    {
        $translation = NewsTranslation::query()
            ->where('locale', $locale)
            ->where('slug', $slug)
            ->first();

        if (! $translation instanceof NewsTranslation) {
            return null;
        }

        return NewsItem::query()->find($translation->news_item_id);
    }

    /**
     * Returns `null` — never throws — when no translation matches; carries
     * no opinion on whether the resolved article is publicly visible.
     */
    public function resolveArticleSlug(string $locale, string $slug): ?Article
    {
        $translation = ArticleTranslation::query()
            ->where('locale', $locale)
            ->where('slug', $slug)
            ->first();

        if (! $translation instanceof ArticleTranslation) {
            return null;
        }

        return Article::query()->find($translation->article_id);
    }

    /**
     * Returns `null` — never throws — when no translation matches; carries
     * no opinion on whether the resolved promotion is publicly visible.
     */
    public function resolvePromotionSlug(string $locale, string $slug): ?Promotion
    {
        $translation = PromotionTranslation::query()
            ->where('locale', $locale)
            ->where('slug', $slug)
            ->first();

        if (! $translation instanceof PromotionTranslation) {
            return null;
        }

        return Promotion::query()->find($translation->promotion_id);
    }

    /**
     * Resolves against `(locale, country_id, full_slug_path)` in one lookup
     * — the composite index the resolution boundary actually needs. Two
     * territories in different countries may share the identical path (a
     * root territory named "Centru" in both Moldova and Georgia, say), so
     * the country must narrow the query itself, never be checked only
     * after an ambiguous match is already in hand.
     */
    private function matchTerritory(string $locale, string $countryCode, string $path): ?Territory
    {
        $country = Country::query()->whereRaw('lower(code) = ?', [Str::lower($countryCode)])->first();

        if (! $country instanceof Country) {
            return null;
        }

        $translation = TerritoryTranslation::query()
            ->where('locale', $locale)
            ->where('country_id', $country->id)
            ->where('full_slug_path', $path)
            ->first();

        if (! $translation instanceof TerritoryTranslation) {
            return null;
        }

        return Territory::query()->with(['translations', 'country'])->find($translation->territory_id);
    }
}
