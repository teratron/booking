<?php

declare(strict_types=1);

namespace App\Support\Seo;

/**
 * The fully-resolved per-language metadata set every indexable page
 * carries: title, meta description, canonical URL, indexability, the Open
 * Graph fields, and the language alternates. `title` and `description` are
 * guaranteed non-empty by the portal's own metadata resolver; `canonicalUrl`
 * is null only when the caller supplied no self URL and no explicit
 * override exists.
 */
final readonly class ResolvedMetadata
{
    /** @param  array<string, string>  $alternates  locale code => absolute URL of this page in that language */
    public function __construct(
        public string $title,
        public string $description,
        public ?string $canonicalUrl,
        public bool $indexable,
        public string $ogTitle,
        public string $ogDescription,
        public ?string $ogImageUrl,
        public array $alternates,
    ) {}
}
