<?php

declare(strict_types=1);

namespace App\Support\Seo;

/**
 * What the portal's own metadata resolver needs from one entity's own
 * record and translation row, extracted once per entity kind so the
 * resolution ladder itself never branches on the concrete model class.
 */
final readonly class SeoEntityContext
{
    public function __construct(
        public SeoEntityType $type,
        public string $name,
        public ?string $territoryName,
        public ?string $ogImage,
        public ?string $explicitTitle,
        public ?string $explicitDescription,
        public ?string $explicitCanonicalUrl,
        public ?bool $explicitIndexable,
        public ?string $explicitOgTitle,
        public ?string $explicitOgDescription,
        public ?string $explicitOgImage,
    ) {}
}
