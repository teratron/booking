<?php

declare(strict_types=1);

namespace App\Support\Cabinet;

/**
 * One contact channel's all-time click total for the owner cabinet's
 * statistics page — the channel's stable registry key, its display label in
 * the currently active locale (or the bare key when no translation row
 * exists for that locale), and the click count itself.
 */
final readonly class ObjectChannelClickCount
{
    public function __construct(
        public string $channelKey,
        public string $label,
        public int $count,
    ) {}
}
