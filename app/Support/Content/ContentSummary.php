<?php

declare(strict_types=1);

namespace App\Support\Content;

use Illuminate\Support\Carbon;

/**
 * The fields a card or a detail-page header needs, regardless of which of
 * the three content types produced them. `Article`, `NewsItem`, and
 * `Promotion` differ in stored fields (moderation status, pin flag, a
 * validity window) but render through one shape — this one — so a future
 * presentation component (a later phase) is written once, against this
 * contract, rather than once per content type.
 */
final readonly class ContentSummary
{
    /**
     * @param  'article'|'news'|'promotion'  $contentType
     */
    public function __construct(
        public string $contentType,
        public int $id,
        public ?string $title,
        public ?string $summary,
        public ?string $coverImageUrl,
        public ?Carbon $publishedAt,
        public ?string $slug,
    ) {}
}
