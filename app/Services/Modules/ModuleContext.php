<?php

declare(strict_types=1);

namespace App\Services\Modules;

/**
 * The scope values a module's resolution ladder is checked against — object,
 * owner, category, and country. Bundled as one value object rather than four
 * loose nullable parameters passed through every call in the resolution
 * chain, including the recursive dependency walk.
 */
final readonly class ModuleContext
{
    public function __construct(
        public ?int $objectId = null,
        public ?int $ownerId = null,
        public ?int $categoryId = null,
        public ?int $countryId = null,
    ) {}
}
