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

/**
 * @param  array<string, mixed>  $overrides  campaign-window and activation
 *                                           columns, so a test can build a banner that is live, retired, or
 *                                           outside its own flight dates without repeating the whole row.
 */
function clickRedirectBanner(array $overrides = []): Banner
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
        ...$overrides,
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

/*
| A banner the selection service would never serve must not be clickable
| either. `/banners/{id}/click` is a stable, guessable integer URL, so a
| retired or out-of-flight campaign left answering it keeps accruing
| billable clicks long after it stopped being shown — and on a portal whose
| revenue is paid placement, an inflated click count on a campaign nobody
| could have seen is a reporting defect, not a cosmetic one. The refusal
| conditions here mirror BannerSelectionService::rankedTiers() exactly.
*/

it('refuses a click on a deactivated banner', function (): void {
    Queue::fake();

    $banner = clickRedirectBanner(['is_active' => false]);

    $this->get(route('banners.click', $banner))->assertNotFound();

    Queue::assertNotPushed(CaptureStatEventJob::class);
});

it('refuses a click on a banner whose campaign has not started', function (): void {
    Queue::fake();

    $banner = clickRedirectBanner([
        'starts_at' => now()->addWeek()->toDateString(),
        'ends_at' => now()->addMonth()->toDateString(),
    ]);

    $this->get(route('banners.click', $banner))->assertNotFound();

    Queue::assertNotPushed(CaptureStatEventJob::class);
});

it('refuses a click on a banner whose campaign has ended', function (): void {
    Queue::fake();

    $banner = clickRedirectBanner([
        'starts_at' => now()->subMonth()->toDateString(),
        'ends_at' => now()->subDay()->toDateString(),
    ]);

    $this->get(route('banners.click', $banner))->assertNotFound();

    Queue::assertNotPushed(CaptureStatEventJob::class);
});

it('still serves a click on the last day of the campaign window', function (): void {
    $banner = clickRedirectBanner([
        'starts_at' => now()->subMonth()->toDateString(),
        'ends_at' => now()->toDateString(),
    ]);

    $this->get(route('banners.click', $banner))
        ->assertRedirect('https://advertiser.example/landing');
});

it('increments the banner\'s lifetime clicks counter atomically alongside the event', function (): void {
    $banner = clickRedirectBanner();

    expect($banner->clicks)->toBe(0);

    $this->get(route('banners.click', $banner));
    $this->get(route('banners.click', $banner));

    expect($banner->fresh()->clicks)->toBe(2);
});

it('never increments clicks for a banner outside its own flight window', function (): void {
    $banner = clickRedirectBanner([
        'starts_at' => now()->subMonth()->toDateString(),
        'ends_at' => now()->subDay()->toDateString(),
    ]);

    $this->get(route('banners.click', $banner))->assertNotFound();

    expect($banner->fresh()->clicks)->toBe(0);
});
