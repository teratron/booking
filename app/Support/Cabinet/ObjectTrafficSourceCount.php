<?php

declare(strict_types=1);

namespace App\Support\Cabinet;

use App\Support\Analytics\TrafficSourceChannel;

/**
 * One traffic-source bucket's visit count for the owner cabinet's statistics
 * page — a coarse channel, the bare referring host (never a full referrer
 * URL), and an optional campaign tag, matching the privacy-minimal
 * traffic-source shape this portal records on a visit's first event only.
 */
final readonly class ObjectTrafficSourceCount
{
    public function __construct(
        public TrafficSourceChannel $channel,
        public ?string $domain,
        public ?string $campaign,
        public int $count,
    ) {}
}
