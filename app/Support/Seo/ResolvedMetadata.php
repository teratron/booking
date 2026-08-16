<?php

declare(strict_types=1);

namespace App\Support\Seo;

/**
 * The fully-resolved per-language metadata set every indexable page
 * carries: title, meta description, canonical URL, indexability, and the
 * Open Graph fields. `title` and `description` are guaranteed non-empty by
 * the portal's own metadata resolver; `canonicalUrl` is null only when the
 * caller supplied no self URL and no explicit override exists.
 */
final readonly class ResolvedMetadata
{
    public function __construct(
        public string $title,
        public string $description,
        public ?string $canonicalUrl,
        public bool $indexable,
        public string $ogTitle,
        public string $ogDescription,
        public ?string $ogImageUrl,
    ) {}
}
