<?php

declare(strict_types=1);

namespace App\Services\Shell;

use App\Models\Country;
use App\Models\Language;
use App\Models\ObjectType;
use App\Models\Territory;
use App\Support\Shell\PublicCountryOption;
use App\Support\Shell\PublicDestinationOption;
use App\Support\Shell\PublicLanguageOption;
use App\Support\Shell\PublicNavigationEntry;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Cache;

/**
 * The public shell's own chrome data — navigation, language switcher,
 * country switcher — read from their respective registries and cached, so
 * every page render costs one cache hit rather than three registry
 * queries. Adding an object type, activating a language, or activating a
 * country is a data operation that appears on the next render, never a
 * code change.
 */
final class PublicShellDataProvider
{
    private const string CACHE_TAG = 'public-shell';

    private const int CACHE_TTL_SECONDS = 3600;

    /** @return list<PublicNavigationEntry> */
    public function navigationGroups(): array
    {
        /** @var list<PublicNavigationEntry> */
        return Cache::tags([self::CACHE_TAG])->remember(
            "public-shell.navigation.{$this->locale()}",
            self::CACHE_TTL_SECONDS,
            fn (): array => array_values(
                ObjectType::query()
                    ->whereNull('parent_id')
                    ->where('is_active', true)
                    ->orderBy('display_order')
                    ->with(['translations', 'children' => function (Relation $relation): void {
                        $relation->where('is_active', true)->orderBy('display_order')->with('translations');
                    }])
                    ->get()
                    ->map($this->mapObjectType(...))
                    ->all(),
            ),
        );
    }

    /** @return list<PublicLanguageOption> */
    public function activeLanguages(): array
    {
        /** @var list<PublicLanguageOption> */
        return Cache::tags([self::CACHE_TAG])->remember(
            'public-shell.languages',
            self::CACHE_TTL_SECONDS,
            fn (): array => array_values(
                Language::query()
                    ->where('is_active', true)
                    ->orderBy('display_order')
                    ->get()
                    ->map(fn (Language $language): PublicLanguageOption => new PublicLanguageOption(
                        code: $language->code,
                        shortLabel: $language->short_label,
                        isPrimary: $language->is_primary,
                    ))
                    ->all(),
            ),
        );
    }

    /** @return list<PublicCountryOption> */
    public function activeCountries(): array
    {
        /** @var list<PublicCountryOption> */
        return Cache::tags([self::CACHE_TAG])->remember(
            "public-shell.countries.{$this->locale()}",
            self::CACHE_TTL_SECONDS,
            fn (): array => array_values(
                Country::query()
                    ->where('is_active', true)
                    ->orderBy('display_order')
                    ->with('translations')
                    ->get()
                    ->map(fn (Country $country): PublicCountryOption => new PublicCountryOption(
                        id: $country->id,
                        code: $country->code,
                        name: (string) $country->name,
                        flagPath: $country->flag_path,
                    ))
                    ->all(),
            ),
        );
    }

    /**
     * Editorially featured top-level territories — the "popular
     * destinations" the footer (portal-wide, no country filter) and the
     * home page (scoped to the visitor's selected country) both link to,
     * each a real, addressable territory landing page. Capped at a
     * sensible list length rather than every featured node.
     *
     * @return list<PublicDestinationOption>
     */
    public function popularDestinations(?int $countryId = null): array
    {
        /** @var list<PublicDestinationOption> */
        return Cache::tags([self::CACHE_TAG])->remember(
            "public-shell.destinations.{$this->locale()}.".($countryId ?? 'all'),
            self::CACHE_TTL_SECONDS,
            fn (): array => array_values(
                Territory::query()
                    ->whereNull('parent_id')
                    ->where('is_active', true)
                    ->where('is_featured', true)
                    ->when($countryId !== null, fn ($query) => $query->where('country_id', $countryId))
                    ->orderBy('display_order')
                    ->with('translations')
                    ->limit(8)
                    ->get()
                    ->map(fn (Territory $territory): PublicDestinationOption => new PublicDestinationOption(
                        id: $territory->id,
                        name: (string) $territory->name,
                    ))
                    ->all(),
            ),
        );
    }

    /**
     * Editorially featured non-top-level territories — the home page's own
     * "popular cities" block, scoped to the visitor's selected country.
     * Depth (a non-null `parent_id`), not a hard-coded level name, is what
     * distinguishes a "city" here, matching the geography domain's own
     * invariant against branching on level names.
     *
     * @return list<PublicDestinationOption>
     */
    public function popularCities(?int $countryId = null): array
    {
        /** @var list<PublicDestinationOption> */
        return Cache::tags([self::CACHE_TAG])->remember(
            "public-shell.cities.{$this->locale()}.".($countryId ?? 'all'),
            self::CACHE_TTL_SECONDS,
            fn (): array => array_values(
                Territory::query()
                    ->whereNotNull('parent_id')
                    ->where('is_active', true)
                    ->where('is_featured', true)
                    ->when($countryId !== null, fn ($query) => $query->where('country_id', $countryId))
                    ->orderBy('display_order')
                    ->with('translations')
                    ->limit(8)
                    ->get()
                    ->map(fn (Territory $territory): PublicDestinationOption => new PublicDestinationOption(
                        id: $territory->id,
                        name: (string) $territory->name,
                    ))
                    ->all(),
            ),
        );
    }

    /**
     * The navigation shape is exactly two levels — a top-level group
     * expands to its child types — so only the top-level call touches the
     * eager-loaded `children` relation; a child's own `children` is never
     * read, which also keeps this clear of a lazy-loading violation on a
     * relation that was never loaded for grandchildren.
     */
    private function mapObjectType(ObjectType $type): PublicNavigationEntry
    {
        return new PublicNavigationEntry(
            id: $type->id,
            key: $type->key,
            name: (string) $type->name,
            children: array_values($type->children->map($this->mapChildObjectType(...))->all()),
        );
    }

    private function mapChildObjectType(ObjectType $type): PublicNavigationEntry
    {
        return new PublicNavigationEntry(
            id: $type->id,
            key: $type->key,
            name: (string) $type->name,
            children: [],
        );
    }

    private function locale(): string
    {
        return app()->getLocale();
    }
}
