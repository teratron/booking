<?php

declare(strict_types=1);

namespace App\Services\Localization;

use App\Models\InterfaceCatalogOverride;
use Illuminate\Contracts\Translation\Loader;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

/**
 * Decorates the framework's own file loader: loads the shipped catalog first,
 * then overlays administrator-edited rows from `interface_catalog_overrides`
 * on top, the database winning on any key both define. This is what makes a
 * catalog entry edited in the back office change the rendered string on the
 * very next request with no deployment step.
 *
 * A request can load several distinct translation groups (this project's own
 * catalog, validation, pagination…), and the translator itself already
 * caches each group for the rest of that request — so the whole override
 * table is fetched at most once per request too, cached indefinitely as one
 * entry rather than one per group, and filtered in memory. A save
 * invalidates that one entry, so there is no manual cache-clear step either.
 *
 * Scoped to the default namespace only: package/vendor translation
 * namespaces (Filament's own UI strings, for example) are not part of this
 * project's interface catalog and are left untouched.
 */
final class DatabaseOverlayLoader implements Loader
{
    public function __construct(private readonly Loader $files) {}

    /**
     * @return array<array-key, mixed>
     */
    public function load($locale, $group, $namespace = null)
    {
        $lines = $this->files->load($locale, $group, $namespace);

        if ($namespace !== null && $namespace !== '*') {
            return $lines;
        }

        foreach ($this->allOverrides() as $override) {
            if ($override['locale'] === $locale && $override['group'] === $group) {
                Arr::set($lines, $override['key'], $override['value']);
            }
        }

        return $lines;
    }

    /**
     * @return list<array{locale: string, group: string, key: string, value: string}>
     */
    private function allOverrides(): array
    {
        return array_values(Cache::rememberForever(
            InterfaceCatalogOverride::cacheKey(),
            fn (): array => InterfaceCatalogOverride::query()
                ->get(['locale', 'group', 'key', 'value'])
                ->map(fn (InterfaceCatalogOverride $override): array => [
                    'locale' => $override->locale,
                    'group' => $override->group,
                    'key' => $override->key,
                    'value' => $override->value,
                ])
                ->all(),
        ));
    }

    public function addNamespace($namespace, $hint)
    {
        $this->files->addNamespace($namespace, $hint);
    }

    public function addJsonPath($path)
    {
        $this->files->addJsonPath($path);
    }

    /**
     * @return array<string, string>
     */
    public function namespaces()
    {
        return $this->files->namespaces();
    }
}
