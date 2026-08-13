<?php

declare(strict_types=1);

namespace App\Support\Content;

/**
 * Implemented by every model a shared content presentation component can
 * render: `Article`, `NewsItem`, and `Promotion`. Adding a fourth content
 * type later means implementing this one method, not adding a fourth
 * rendering branch to whatever component consumes {@see ContentSummary}.
 */
interface Summarizable
{
    public function toContentSummary(): ContentSummary;
}
