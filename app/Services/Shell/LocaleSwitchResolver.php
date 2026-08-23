<?php

declare(strict_types=1);

namespace App\Services\Shell;

use App\Models\Object_;
use App\Models\ObjectType;
use App\Models\Territory;
use App\Services\Seo\MetadataResolver;
use App\Services\Seo\PublicSlugResolver;
use App\Services\Seo\PublicUrlGenerator;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;

/**
 * Computes the target URL for the language switcher: the same route and
 * parameters the visitor is already on, with only the `lang` segment
 * swapped, so switching language preserves position rather than returning
 * to the home page.
 *
 * Per-entity slug translation — resolving a *different* slug for the same
 * territory or object in the target language, with a fallback to the
 * nearest translated ancestor when one is missing — now exists in
 * {@see PublicUrlGenerator}: the object and territory pages address by
 * translated slug, so a bare `lang` swap would keep the current locale's
 * slug and 404. Every other public route stays language-neutral by
 * construction (a plain `lang` swap already resolves it correctly), so
 * only these two routes need the translated lookup.
 *
 * Bound as a singleton in `AppServiceProvider` specifically so the memo
 * below actually spans a whole request: {@see MetadataResolver::alternates()}
 * calls `targetUrl()` once per active language, and the header's desktop
 * and mobile language switchers each independently resolve this class
 * straight from the container (`app(LocaleSwitchResolver::class)`) rather
 * than receiving it by injection — three separate call sites that would
 * otherwise each pay for its own from-scratch slug resolution. The current
 * object/territory is resolved and its `translations` eager-loaded at
 * most once per request and reused across every one of those calls.
 * Skipping this — either the singleton binding or the memo itself —
 * reproducibly pushed the territory page well over its 30-query budget.
 */
final class LocaleSwitchResolver
{
    private ?Object_ $cachedObject = null;

    private bool $objectResolutionAttempted = false;

    private ?Territory $cachedTerritory = null;

    private ?ObjectType $cachedObjectType = null;

    private bool $territoryResolutionAttempted = false;

    public function __construct(
        private readonly PublicSlugResolver $slugResolver,
        private readonly PublicUrlGenerator $urls,
    ) {}

    /**
     * Seeds the resolver's own memo with an entity the caller already has
     * in hand — {@see MetadataResolver::resolve()} always
     * does, since it already fetched the entity (with `translations`
     * eager-loaded) to render the page itself. A no-op once the matching
     * resolution has already run, so calling this more than once, or after
     * `targetUrl()` has already resolved the same kind of entity, is safe.
     *
     * A `Territory` is always seeded as the *plain* territory page — the
     * typed-catalog-within-a-territory composite never calls this, since
     * seeding only the territory half without its object type would make
     * `targetUrl()` build a URL missing the type segment.
     */
    public function seed(Object_|Territory $entity): void
    {
        if ($entity instanceof Object_ && ! $this->objectResolutionAttempted) {
            $entity->loadMissing('translations');
            $this->cachedObject = $entity;
            $this->objectResolutionAttempted = true;
        }

        if ($entity instanceof Territory && ! $this->territoryResolutionAttempted) {
            $entity->loadMissing('translations', 'country');
            $this->cachedTerritory = $entity;
            $this->territoryResolutionAttempted = true;
        }
    }

    public function targetUrl(Request $request, string $targetLang): string
    {
        $route = $request->route();

        if (! $route instanceof Route || $route->getName() === null) {
            return url("/{$targetLang}");
        }

        $url = $this->translatedTargetUrl($route, $targetLang) ?? $this->genericTargetUrl($route, $targetLang);
        $query = $request->query();

        return $query === [] ? $url : $url.'?'.http_build_query($query);
    }

    private function genericTargetUrl(Route $route, string $targetLang): string
    {
        $parameters = $route->parameters();
        $parameters['lang'] = $targetLang;

        return route((string) $route->getName(), $parameters);
    }

    /**
     * Returns `null` — never a broken URL — when the current slug does not
     * resolve (a stale link) or the resolved entity carries no translation
     * in `$targetLang`; the caller falls back to the generic same-slug swap
     * in that case, matching this resolver's own prior behaviour.
     */
    private function translatedTargetUrl(Route $route, string $targetLang): ?string
    {
        $currentLang = $route->parameter('lang');

        if (! is_string($currentLang)) {
            return null;
        }

        return match ($route->getName()) {
            'public.objects.show' => $this->objectTargetUrl($route, $currentLang, $targetLang),
            'public.territories.show' => $this->territoryTargetUrl($route, $currentLang, $targetLang),
            default => null,
        };
    }

    private function objectTargetUrl(Route $route, string $currentLang, string $targetLang): ?string
    {
        $object = $this->resolveCurrentObject($route, $currentLang);

        return $object instanceof Object_ ? $this->urls->objectUrl($object, $targetLang) : null;
    }

    private function resolveCurrentObject(Route $route, string $currentLang): ?Object_
    {
        if ($this->objectResolutionAttempted) {
            return $this->cachedObject;
        }

        $this->objectResolutionAttempted = true;

        $slug = $route->parameter('slug');

        if (! is_string($slug)) {
            return null;
        }

        $object = $this->slugResolver->resolveObjectSlug($currentLang, $slug);

        if ($object instanceof Object_) {
            $object->loadMissing('translations');
            $this->cachedObject = $object;
        }

        return $this->cachedObject;
    }

    /**
     * Covers both the plain territory page and the typed-catalog-within-a-
     * territory page (`{settlement}/{type}`) — {@see PublicSlugResolver}'s
     * own resolution already disambiguates the two from the same `path`
     * segment, so this mirrors that split rather than re-deriving it.
     */
    private function territoryTargetUrl(Route $route, string $currentLang, string $targetLang): ?string
    {
        $this->resolveCurrentTerritory($route, $currentLang);

        if (! $this->cachedTerritory instanceof Territory) {
            return null;
        }

        return $this->cachedObjectType instanceof ObjectType
            ? $this->urls->typedCatalogUrl($this->cachedTerritory, $this->cachedObjectType, $targetLang)
            : $this->urls->territoryUrl($this->cachedTerritory, $targetLang);
    }

    private function resolveCurrentTerritory(Route $route, string $currentLang): void
    {
        if ($this->territoryResolutionAttempted) {
            return;
        }

        $this->territoryResolutionAttempted = true;

        $country = $route->parameter('country');
        $path = $route->parameter('path');

        if (! is_string($country) || ! is_string($path)) {
            return;
        }

        $resolved = $this->slugResolver->resolveTerritoryPath($currentLang, $country, $path);

        if ($resolved === null) {
            return;
        }

        $resolved['territory']->loadMissing('translations', 'country');
        $this->cachedTerritory = $resolved['territory'];

        if ($resolved['objectType'] instanceof ObjectType) {
            $resolved['objectType']->loadMissing('translations');
            $this->cachedObjectType = $resolved['objectType'];
        }
    }
}
