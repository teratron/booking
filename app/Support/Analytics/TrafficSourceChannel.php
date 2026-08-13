<?php

declare(strict_types=1);

namespace App\Support\Analytics;

/**
 * The six values `stat_events.source_channel`'s check constraint allows —
 * deliberately coarse, per the specification's traffic-source shape: a
 * classification bucket, never the mechanism (search engine name, social
 * network) that would make the row identify anything more specific than
 * "how this visit arrived".
 */
enum TrafficSourceChannel: string
{
    case Direct = 'direct';
    case Search = 'search';
    case Social = 'social';
    case Referral = 'referral';
    case Internal = 'internal';
    case Campaign = 'campaign';
}
