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
 */
final class BannerClickController extends Controller
{
    public function __invoke(Banner $banner, EventCaptureService $events): RedirectResponse
    {
        $events->capture('banner_click', $banner);

        return redirect()->away($banner->destination_link);
    }
}
