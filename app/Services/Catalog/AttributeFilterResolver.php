<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Models\ObjectType;
use App\Support\Catalog\CatalogSearchCriteria;

/**
 * Resolves a visitor's raw type-attribute filter selection against the
 * object type that declares those attributes, so only filters the type
 * actually declares — shaped the way that attribute is actually stored —
 * ever reach {@see CatalogQueryService}.
 *
 * {@see CatalogSearchCriteria} states that facet
 * resolution is the calling page's job, not the DTO's. This is that
 * resolution for the `attributes` bag, extracted from the catalog page so
 * the public API can reuse the one implementation when it grows its own
 * attribute filters.
 *
 * Three things go wrong without it, and every one of them is reachable
 * from a hand-edited query string:
 *
 * - A range filter against a text attribute, or a non-numeric bound
 *   ("?attrs[foo][min]=abc"), reaches a `::numeric` cast that Postgres
 *   refuses — the catalog page answers 500 instead of ignoring a filter it
 *   cannot honour.
 * - A boolean attribute stores as a real JSON boolean, so `->>` reads it
 *   back as the text `true`/`false`. A raw `1` from the query string
 *   compares as `'1' = 'true'` and silently matches nothing.
 * - An undeclared key is a filter dimension nothing can satisfy, and each
 *   distinct one mints its own catalog cache entry.
 *
 * Anything not declared, or declared but unsatisfiable as written, is
 * dropped rather than refused: a stale or hand-edited filter should degrade
 * to a broader result set, never to an error page.
 */
final class AttributeFilterResolver
{
    /**
     * The subset of $raw that $type declares, coerced to the comparison
     * shape each declared type is stored in.
     *
     * $raw's keys are declared as `array-key`, not `string`: it arrives
     * straight from a Livewire `#[Url]` property bound to a query string,
     * and PHP's own array-key coercion turns a segment like `attrs[0]=x`
     * into an integer key regardless of what the rest of the request looks
     * like — the `is_string()` guard below is real, not defensive noise.
     *
     * @param  array<array-key, mixed>  $raw
     * @return array<string, array{min?: float, max?: float}|scalar>
     */
    public function resolve(?ObjectType $type, array $raw): array
    {
        if (! $type instanceof ObjectType || $raw === []) {
            return [];
        }

        $declared = $this->declaredTypesByKey($type);
        $resolved = [];

        foreach ($raw as $key => $value) {
            if (! is_string($key) || ! array_key_exists($key, $declared)) {
                continue;
            }

            $filter = $this->coerce($declared[$key], $value);

            if ($filter !== null) {
                $resolved[$key] = $filter;
            }
        }

        return $resolved;
    }

    /**
     * The type's declared attribute keys mapped to their declared value
     * type. A malformed schema entry (no key, no type) is skipped rather
     * than assumed to be text — an attribute nothing can describe is an
     * attribute nothing should be able to filter by.
     *
     * @return array<string, string>
     */
    private function declaredTypesByKey(ObjectType $type): array
    {
        $declared = [];

        /** @var list<array<string, mixed>> $schema */
        $schema = $type->attribute_schema ?? [];

        foreach ($schema as $attribute) {
            $key = $attribute['key'] ?? null;
            $declaredType = $attribute['type'] ?? null;

            if (is_string($key) && $key !== '' && is_string($declaredType)) {
                $declared[$key] = $declaredType;
            }
        }

        return $declared;
    }

    /**
     * One filter value, or null when this attribute cannot be filtered the
     * way the request asked.
     *
     * @return array{min?: float, max?: float}|float|string|null
     */
    private function coerce(string $declaredType, mixed $value): array|float|string|null
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        return match ($declaredType) {
            'number' => $this->coerceNumber($value),
            'boolean' => $this->coerceBoolean($value),
            default => is_scalar($value) ? (string) $value : null,
        };
    }

    /**
     * A numeric attribute accepts either a range or an exact value. Only
     * bounds that are genuinely numeric survive — the `::numeric` cast
     * downstream has no defined behaviour for anything else.
     *
     * @return array{min?: float, max?: float}|float|null
     */
    private function coerceNumber(mixed $value): array|float|null
    {
        if (is_array($value)) {
            $range = [];

            foreach (['min', 'max'] as $bound) {
                if (isset($value[$bound]) && is_numeric($value[$bound])) {
                    $range[$bound] = (float) $value[$bound];
                }
            }

            return $range === [] ? null : $range;
        }

        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * Booleans are stored as real JSON booleans, so `attributes ->> key`
     * reads back the text `true` or `false` — never `1` or `0`. The
     * comparison value has to be that same text for the filter to match
     * anything at all.
     *
     * An unrecognised value drops the filter rather than defaulting to
     * false: "?attrs[has_pool]=maybe" must not silently become a search for
     * objects without a pool.
     */
    private function coerceBoolean(mixed $value): ?string
    {
        $normalized = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        return $normalized === null ? null : ($normalized ? 'true' : 'false');
    }
}
