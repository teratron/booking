<?php

declare(strict_types=1);

namespace App\Services\Authorization;

/**
 * The union of one actor's grants of a single permission, flattened into the
 * three axes a target can be bounded by.
 *
 * Grants are additive: an administrator holding both a Moldova grant and a
 * dining-category grant reaches Moldovan objects of any category *and* dining
 * objects in any country. Collapsing them to an intersection would deny access
 * the stored grants plainly allow, so the axes are OR-ed, never AND-ed.
 */
final class ScopeConstraint
{
    /**
     * @param  list<int>  $countryIds
     * @param  list<int>  $territoryIds  already expanded to include every descendant
     * @param  list<int>  $categoryIds
     */
    public function __construct(
        public readonly bool $isUnrestricted,
        public readonly array $countryIds = [],
        public readonly array $territoryIds = [],
        public readonly array $categoryIds = [],
    ) {}

    public static function unrestricted(): self
    {
        return new self(true);
    }

    public static function nothing(): self
    {
        return new self(false);
    }

    /**
     * True when the actor holds the permission but no grant of it reaches any
     * target — the deny-everything case, distinct from the unrestricted one.
     */
    public function reachesNothing(): bool
    {
        return ! $this->isUnrestricted
            && $this->countryIds === []
            && $this->territoryIds === []
            && $this->categoryIds === [];
    }
}
