<?php

declare(strict_types=1);

use App\Jobs\CaptureStatEventJob;
use App\Models\Banner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Banner Click Redirect
|--------------------------------------------------------------------------
|
| The one route every served banner creative links through instead of its
| destination directly, so a click is counted server-side — at the point of
| redirect, never relying on client-side-only counting — before the visitor
| is forwarded on to the advertiser.
|
*/

function clickRedirectBanner(): Banner
{
    $slotId = DB::table('banner_slots')->insertGetId([
        'key' => 'click_probe', 'surfaces' => json_encode(['home']),
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $bannerId = DB::table('banners')->insertGetId([
        'banner_slot_id' => $slotId, 'name' => 'Click probe banner', 'advertiser' => 'Acme',
        'destination_link' => 'https://advertiser.example/landing',
        'starts_at' => now()->subDay()->toDateString(), 'ends_at' => now()->addMonth()->toDateString(),
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return Banner::query()->findOrFail($bannerId);
}

it('forwards the visitor to the banner destination_link', function (): void {
    $banner = clickRedirectBanner();

    $response = $this->get(route('banners.click', $banner));

    $response->assertRedirect('https://advertiser.example/landing');
});

it('records exactly one banner_click event at the point of redirect', function (): void {
    Queue::fake();

    $banner = clickRedirectBanner();

    $this->get(route('banners.click', $banner));

    Queue::assertPushed(CaptureStatEventJob::class, 1);
});

it('returns 404 for a banner id that does not exist', function (): void {
    $response = $this->get('/banners/999999/click');

    $response->assertNotFound();
});
