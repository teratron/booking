<?php

declare(strict_types=1);

namespace App\Support\Analytics;

/**
 * The resolved, already-coarsened first-touch tuple {@see
 * \App\Services\Analytics\TrafficSourceRecorder} hands back — a channel
 * bucket, a bare host, and an optional campaign tag. Never a full referrer
 * URL: whatever produced this value object has already discarded the
 * scheme, path, query string, and fragment before this object exists.
 */
final class TrafficSource
{
    public function __construct(
        public readonly TrafficSourceChannel $channel,
        public readonly ?string $domain,
        public readonly ?string $campaign,
    ) {}
}
