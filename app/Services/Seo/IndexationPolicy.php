<?php

declare(strict_types=1);

namespace App\Services\Seo;

use App\Models\CatalogFilterPromotion;
use Illuminate\Support\Facades\Cache;

/**
 * Whether a filtered catalog view is indexable — data-driven, never a
 * hard-coded per-route decision. Filtered catalog views are not indexable
 * by default: zero active filters is the plain browse view (indexable), a
 * free-text search is never indexable regardless of filters, exactly one
 * active filter is indexable only once an administrator has promoted that
 * specific value, and two or more active filters is never indexable —
 * promotion applies to a single filter, never a combination.
 */
final class IndexationPolicy
{
    private const string CACHE_KEY = 'catalog_filter_promotions:active';

    /**
     * @param  array<string, scalar>  $activeFilters  filter key => value, already reduced to the non-empty dimensions only
     */
    public function catalogIndexable(array $activeFilters, bool $hasSearchText): bool
    {
        if ($hasSearchText) {
            return false;
        }

        if ($activeFilters === []) {
            return true;
        }

        if (count($activeFilters) > 1) {
            return false;
        }

        $key = array_key_first($activeFilters);

        return in_array($key.'='.$activeFilters[$key], $this->activeSignatures(), true);
    }

    /** @return list<string> */
    private function activeSignatures(): array
    {
        /** @var list<string> $signatures */
        $signatures = Cache::rememberForever(
            self::CACHE_KEY,
            static fn (): array => CatalogFilterPromotion::query()->where('is_active', true)->pluck('signature')->all(),
        );

        return $signatures;
    }

    /**
     * Drops the cached promotion list — call after any write to
     * {@see CatalogFilterPromotion}.
     */
    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
