<?php

declare(strict_types=1);

namespace App\Support\Cabinet;

use App\Services\Cabinet\ObjectDashboardService;
use Illuminate\Support\Carbon;

/**
 * Everything the owner cabinet's landing screen renders for the object
 * currently selected as the panel's tenant — assembled once by
 * {@see ObjectDashboardService} so the page itself
 * stays a thin presenter over this single read.
 */
final readonly class ObjectDashboardSummary
{
    public function __construct(
        public string $objectName,
        public ?string $packageName,
        public ?string $tierLabel,
        public ?Carbon $placementExpiresAt,
        public bool $expiryWarningActive,
        public ?int $expiryWarningDaysRemaining,
        /**
         * Where this object currently ranks in the public catalog. Null
         * means "not yet computable" — the public catalog page that would
         * give a position meaning does not exist in this codebase yet, so
         * this stays unset rather than asserting a number nothing can
         * currently defend.
         */
        public ?int $catalogPosition,
        public int $viewsToday,
        public int $viewsThisWeek,
        public int $viewsThisMonth,
        public int $viewsAllTime,
        public int $messengerClicks,
        public int $websiteClicks,
        public string $availabilityStatus,
    ) {}
}
