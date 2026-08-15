<?php

declare(strict_types=1);

namespace App\Services\Seo;

use App\Models\Country;
use App\Models\Object_;
use App\Models\ObjectTranslation;
use App\Models\ObjectType;
use App\Models\ObjectTypeTranslation;
use App\Models\Territory;
use App\Models\TerritoryTranslation;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * The single place every public page, card, and breadcrumb builds a
 * territory, object, or country URL — never a raw {@see Route()} call at
 * the call site. Centralizing generation here is what keeps the grammar in
 * {@see PublicSlugResolver} (the inbound half of the same contract) the
 * only place that decides what a URL segment means.
 *
 * Every method returns `null` rather than throwing when the underlying
 * route is not registered (mirrors this codebase's existing
 * `Route::has()` guard convention) or when the entity has no translation
 * for the requested language — a caller renders that as an absent link,
 * never a broken one. None of these methods query beyond what the caller
 * already loaded except a defensive fallback fetch of `$territory->country`
 * when that relation was not eager-loaded.
 */
final class PublicUrlGenerator
{
    /**
     * The country segment is the country's own stable ISO code, not a
     * translated slug: the URL grammar states no per-language country name
     * requirement, and a code is already unique, stable, and identical in
     * every language — unlike a translated name, it never forces a
     * cross-language redirect when a translation changes.
     */
    public function countrySegment(Country $country): string
    {
        return Str::lower($country->code);
    }

    /**
     * The territory's own landing URL: `country` (its stable ISO code) plus
     * `path` (the territory's own cached `full_slug_path` in `$locale`).
     * Guarantees the returned URL resolves through
     * {@see PublicSlugResolver::resolveTerritoryPath()} for the same
     * `$locale`. Returns `null`, never throws, when the route is not
     * registered, the territory carries no translation for `$locale`, or
     * its country cannot be resolved.
     */
    public function territoryUrl(Territory $territory, ?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();

        if (! Route::has('public.territories.show')) {
            return null;
        }

        /** @var ?TerritoryTranslation $translation */
        $translation = $territory->translate($locale, withFallback: false);
        $path = $translation?->full_slug_path;
        $country = $territory->relationLoaded('country') ? $territory->country : $territory->country()->first();

        if ($path === null || ! $country instanceof Country) {
            return null;
        }

        return route('public.territories.show', [
            'lang' => $locale,
            'country' => $this->countrySegment($country),
            'path' => $path,
        ]);
    }

    /**
     * The typed-catalog URL within a territory — `{settlement}/{type}` —
     * appends the object type's own per-language slug to the territory's
     * path. Both segments must resolve in the same language or the URL is
     * withheld rather than mixing languages mid-path.
     */
    public function typedCatalogUrl(Territory $territory, ObjectType $objectType, ?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();

        if (! Route::has('public.territories.show')) {
            return null;
        }

        /** @var ?TerritoryTranslation $territoryTranslation */
        $territoryTranslation = $territory->translate($locale, withFallback: false);
        $territoryPath = $territoryTranslation?->full_slug_path;
        /** @var ?ObjectTypeTranslation $typeTranslation */
        $typeTranslation = $objectType->translate($locale, withFallback: false);
        $typeSlug = $typeTranslation?->slug;
        $country = $territory->relationLoaded('country') ? $territory->country : $territory->country()->first();

        if ($territoryPath === null || $typeSlug === null || ! $country instanceof Country) {
            return null;
        }

        return route('public.territories.show', [
            'lang' => $locale,
            'country' => $this->countrySegment($country),
            'path' => "{$territoryPath}/{$typeSlug}",
        ]);
    }

    /**
     * The country landing URL is not its own page composition — per
     * `CountryPreferenceController`'s own established reading, a country is
     * a dimension of the territory tree, not one specific node in it — so
     * "the country landing page" resolves to that country's own top-level
     * territory with the lowest display order. A country with no territory
     * yet has nothing to land on.
     */
    public function countryLandingUrl(Country $country, ?string $locale = null): ?string
    {
        $landing = Territory::query()
            ->where('country_id', $country->id)
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('display_order')
            ->with(['translations', 'country'])
            ->first();

        if (! $landing instanceof Territory) {
            return null;
        }

        return $this->territoryUrl($landing, $locale);
    }

    /**
     * The object's own flat profile URL — `/{lang}/o/{slug}` — stable under
     * any territory reassignment, per the grammar's own addressing
     * invariant. Returns `null`, never throws, when the route is not
     * registered or the object carries no translation for `$locale`.
     */
    public function objectUrl(Object_ $object, ?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();

        if (! Route::has('public.objects.show')) {
            return null;
        }

        /** @var ?ObjectTranslation $translation */
        $translation = $object->translate($locale, withFallback: false);
        $slug = $translation?->slug;

        if ($slug === null) {
            return null;
        }

        return route('public.objects.show', ['lang' => $locale, 'slug' => $slug]);
    }
}
