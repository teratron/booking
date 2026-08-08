<?php

declare(strict_types=1);

namespace App\Services\Localization;

use Illuminate\Support\Facades\File;

/**
 * Reads the file-shipped interface catalogs under `resources/lang` — the
 * "files supply the shipped defaults" half of the overlay a database-backed
 * loader adds administrator edits on top of. The canonical key set for a
 * catalog group is whatever the primary language's file declares: a
 * language activated later with no file of its own simply has no keys of
 * its own to list, and every one of them resolves through fallback until an
 * administrator fills an override in.
 */
final class InterfaceCatalog
{
    public function __construct(private readonly LanguageRegistry $languages) {}

    /**
     * @return list<string> catalog group names, e.g. ["panel"] — one per
     *                      `resources/lang/{primary}/*.php` file
     */
    public function groups(): array
    {
        $directory = lang_path($this->languages->primaryLocale());

        if (! File::isDirectory($directory)) {
            return [];
        }

        return array_values(collect(File::files($directory))
            ->filter(fn ($file) => $file->getExtension() === 'php')
            ->map(fn ($file) => $file->getFilenameWithoutExtension())
            ->sort()
            ->all());
    }

    /**
     * The shipped catalog for one group in one locale, flattened to dot keys.
     * Empty when that locale has no file for the group at all — the state
     * every language starts in the moment it is activated.
     *
     * @return array<string, string>
     */
    public function shipped(string $group, string $locale): array
    {
        $path = lang_path("{$locale}/{$group}.php");

        if (! File::exists($path)) {
            return [];
        }

        /** @var array<array-key, mixed> $raw */
        $raw = require $path;

        return $this->flatten($raw);
    }

    /**
     * The canonical key set for a group — every key the primary language's
     * file declares. Every other language's editor row list is driven by
     * this, not by that language's own (possibly empty or partial) file.
     *
     * @return array<string, string>
     */
    public function canonicalKeys(string $group): array
    {
        return $this->shipped($group, $this->languages->primaryLocale());
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<string, string>
     */
    private function flatten(array $values, string $prefix = ''): array
    {
        $flat = [];

        foreach ($values as $key => $value) {
            $dotKey = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

            if (is_array($value)) {
                $flat += $this->flatten($value, $dotKey);

                continue;
            }

            $flat[$dotKey] = (string) $value;
        }

        return $flat;
    }
}
