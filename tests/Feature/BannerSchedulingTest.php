<?php

declare(strict_types=1);

use App\Models\Banner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Banner Scheduling Columns
|--------------------------------------------------------------------------
|
| Whether a banner is *eligible to serve* right now is a selection-service
| concern that does not exist yet. This model layer only has to guarantee
| that a banner outside its own window, or explicitly deactivated, remains
| an ordinary, fully readable record with its columns cast as declared.
|
*/

function schedulingBannerSlot(): int
{
    return DB::table('banner_slots')->insertGetId([
        'key' => 'scheduling_probe', 'surfaces' => json_encode(['home']),
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

it('remains a normal, readable record once its window has expired, with dates cast to Carbon', function (): void {
    $bannerId = DB::table('banners')->insertGetId([
        'banner_slot_id' => schedulingBannerSlot(), 'name' => 'Expired banner', 'advertiser' => 'Acme',
        'destination_link' => 'https://example.test',
        'starts_at' => now()->subMonths(2)->toDateString(),
        'ends_at' => now()->subMonth()->toDateString(),
        'is_active' => true, 'impressions' => 42, 'clicks' => 3,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $banner = Banner::query()->findOrFail($bannerId);

    expect($banner->starts_at)->toBeInstanceOf(Carbon::class)
        ->and($banner->ends_at)->toBeInstanceOf(Carbon::class)
        ->and($banner->ends_at->isPast())->toBeTrue()
        ->and($banner->is_active)->toBeTrue()
        ->and($banner->impressions)->toBe(42)
        ->and($banner->clicks)->toBe(3);
});

it('remains a normal, readable record once explicitly deactivated, with is_active cast to bool', function (): void {
    $bannerId = DB::table('banners')->insertGetId([
        'banner_slot_id' => schedulingBannerSlot(), 'name' => 'Deactivated banner', 'advertiser' => 'Acme',
        'destination_link' => 'https://example.test',
        'starts_at' => now()->toDateString(), 'ends_at' => now()->addMonth()->toDateString(),
        'is_active' => false,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $banner = Banner::query()->findOrFail($bannerId);

    expect($banner->is_active)->toBeFalse()
        ->and($banner->is_active)->toBeBool()
        ->and($banner->name)->toBe('Deactivated banner')
        ->and($banner->slot()->first()?->id)->toBe(schedulingBannerSlotIdFor($banner));
});

/**
 * Resolves the slot id the banner was created with, purely so the assertion
 * above exercises `slot(): BelongsTo` itself rather than restating the id
 * the test already knows.
 */
function schedulingBannerSlotIdFor(Banner $banner): int
{
    return (int) DB::table('banners')->where('id', $banner->id)->value('banner_slot_id');
}

it('is still a normal, readable record before its window has started', function (): void {
    $bannerId = DB::table('banners')->insertGetId([
        'banner_slot_id' => schedulingBannerSlot(), 'name' => 'Future banner', 'advertiser' => 'Acme',
        'destination_link' => 'https://example.test',
        'starts_at' => now()->addMonth()->toDateString(),
        'ends_at' => now()->addMonths(2)->toDateString(),
        'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $banner = Banner::query()->findOrFail($bannerId);

    expect($banner->starts_at->isFuture())->toBeTrue()
        ->and($banner->trashed())->toBeFalse();
});
