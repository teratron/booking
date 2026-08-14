<?php

declare(strict_types=1);

namespace App\Support\Cabinet;

use App\Services\Cabinet\ObjectStatisticsService;

/**
 * Everything the owner cabinet's dedicated statistics page renders for the
 * object currently selected as the panel's tenant — all-time page views,
 * photo views, the per-channel contact-click breakdown, the traffic-source
 * breakdown, and the favorite count. Assembled once by
 * {@see ObjectStatisticsService} so the page itself
 * stays a thin presenter over this single read.
 */
final readonly class ObjectStatisticsSummary
{
    /**
     * @param  list<ObjectChannelClickCount>  $channelClicks
     * @param  list<ObjectTrafficSourceCount>  $trafficSources
     */
    public function __construct(
        public string $objectName,
        public int $pageViews,
        public int $photoViews,
        public int $contactClicksTotal,
        public array $channelClicks,
        public array $trafficSources,
        public int $favoriteCount,
    ) {}
}
