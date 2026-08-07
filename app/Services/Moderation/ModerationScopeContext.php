<?php

declare(strict_types=1);

namespace App\Services\Moderation;

/**
 * The scope values a submitted change is resolved against — object, owner,
 * category, and country. Bundled as one value object rather than four loose
 * nullable parameters passed through the resolver's ladder walk, mirroring
 * `Services\Modules\ModuleContext`'s shape for the identical axes.
 *
 * Kept as its own type rather than reusing `ModuleContext`: the two ladders
 * share the same axes but not the same table or the same lifecycle, and a
 * shared value type would couple moderation and feature-module resolution
 * to one class that neither fully owns — a future divergence in either
 * ladder's shape would then force a change in the other's code too.
 */
final readonly class ModerationScopeContext
{
    public function __construct(
        public ?int $objectId = null,
        public ?int $ownerId = null,
        public ?int $categoryId = null,
        public ?int $countryId = null,
    ) {}
}
