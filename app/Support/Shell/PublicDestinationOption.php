<?php

declare(strict_types=1);

namespace App\Support\Shell;

use App\Services\Seo\PublicUrlGenerator;

/**
 * One entry in the footer's "popular destinations" list and the home
 * page's "popular cities" block — a territory, linking to its own landing
 * page. `$url` is resolved once, at cache-build time, rather than in the
 * view: {@see PublicUrlGenerator} needs the territory's
 * loaded `country` relation, which this DTO does not carry forward.
 */
final readonly class PublicDestinationOption
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $url,
    ) {}
}
