<?php

declare(strict_types=1);

use App\Http\Controllers\BannerClickController;
use App\Models\Banner;
use App\Services\Advertising\BannerSelectionService;
use App\Services\Analytics\PortalReportingService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `banners.impressions` and `banners.clicks` shipped as columns with no
     * writer — {@see BannerSelectionService::forSlot()} and
     * {@see BannerClickController} now increment them atomically alongside
     * the analytics event each already records, but that fixes only
     * impressions and clicks from this point forward. Any environment
     * carrying banner traffic from before this migration — production, or a
     * shared staging database seeded with demo volume — already holds the
     * true historical totals in `stat_dailies`, the same rollup
     * {@see PortalReportingService::bannerClickThroughRate()} reads for the
     * dimensional report. Summing it once here is a one-time catch-up, not
     * a second source of truth: from this deploy on, the two numbers are
     * written by the same request and can never legitimately disagree
     * again.
     *
     * A fresh install has no `stat_dailies` rows yet, so this is a correct
     * no-op there — every banner already reads 0, and the query below sums
     * to 0 for all of them.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            update banners
            set impressions = coalesce((
                select sum(sd.count)
                from stat_dailies sd
                where sd.subject_type = ?
                  and sd.subject_id = banners.id
                  and sd.kind = 'banner_impression'
            ), 0),
            clicks = coalesce((
                select sum(sd.count)
                from stat_dailies sd
                where sd.subject_type = ?
                  and sd.subject_id = banners.id
                  and sd.kind = 'banner_click'
            ), 0)
            SQL, [Banner::class, Banner::class]);
    }

    /**
     * Reverse the migrations.
     *
     * Deliberately a no-op: the columns already existed before this
     * migration, empty. Zeroing them back out on rollback would discard the
     * same historical totals a second run of `up()` would just recompute,
     * for no benefit — and would throw away any impressions/clicks the live
     * increments above have accrued since this migration ran, which the
     * historical sum alone cannot reconstruct.
     */
    public function down(): void {}
};
