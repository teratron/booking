<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Banner;
use App\Services\Analytics\EventCaptureService;
use Illuminate\Http\RedirectResponse;

/**
 * The click-through every served banner creative links to instead of its
 * destination directly, so a click is counted server-side — the one point
 * no ad blocker or client-side script failure can suppress — before the
 * visitor ever reaches the advertiser's own page.
 *
 * A creative that may no longer serve may no longer be counted either. The
 * eligibility test is {@see Banner::scopeLive()} — the same scope the
 * selection query narrows by — rather than a second copy of the window
 * comparison here, so the serve side and the count side cannot disagree
 * about which campaigns are running.
 */
final class BannerClickController extends Controller
{
    public function __invoke(Banner $banner, EventCaptureService $events): RedirectResponse
    {
        // Re-asked as a query rather than read off the bound model: route
        // model binding resolves a banner by id alone, and applying the
        // window at binding time would mean narrowing every other binding
        // of this model too — including the back-office resource, whose
        // whole job is editing campaigns that are not currently running.
        abort_unless(Banner::query()->live()->whereKey($banner->getKey())->exists(), 404);

        $events->capture('banner_click', $banner);

        // The lifetime counter the back-office list, its click-through-rate
        // column, and the export all read — see the matching comment on
        // {@see \App\Services\Advertising\BannerSelectionService::forSlot()}
        // for why an atomic same-request increment, not a rollup, is what
        // keeps this in agreement with the event pipeline.
        $banner->increment('clicks');

        return redirect()->away($banner->destination_link);
    }
}
