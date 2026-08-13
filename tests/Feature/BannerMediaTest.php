<?php

declare(strict_types=1);

use App\Models\Banner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Banner Creative Media
|--------------------------------------------------------------------------
|
| Desktop and mobile creatives attach through spatie/laravel-medialibrary,
| the same mechanism catalog photographs use, as two independently-
| uploadable single-file collections on the same banner row — not a shared
| collection distinguished after the fact, and not path columns.
|
*/

function mediaTestBanner(): Banner
{
    $slotId = DB::table('banner_slots')->insertGetId([
        'key' => 'media_probe', 'surfaces' => json_encode(['home']),
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $bannerId = DB::table('banners')->insertGetId([
        'banner_slot_id' => $slotId, 'name' => 'Media probe banner', 'advertiser' => 'Acme',
        'destination_link' => 'https://example.test',
        'starts_at' => now()->toDateString(), 'ends_at' => now()->addMonth()->toDateString(),
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return Banner::query()->findOrFail($bannerId);
}

it('stores desktop and mobile creatives as two independent single-file collections', function (): void {
    Storage::fake('public');

    $banner = mediaTestBanner();

    $banner->addMedia(UploadedFile::fake()->image('desktop.jpg', 1920, 600))
        ->toMediaCollection('desktop_creative');

    $banner->addMedia(UploadedFile::fake()->image('mobile.jpg', 600, 800))
        ->toMediaCollection('mobile_creative');

    $reloaded = Banner::query()->findOrFail($banner->id);

    expect($reloaded->getMedia('desktop_creative'))->toHaveCount(1)
        ->and($reloaded->getMedia('mobile_creative'))->toHaveCount(1)
        ->and($reloaded->getFirstMedia('desktop_creative')?->file_name)->toBe('desktop.jpg')
        ->and($reloaded->getFirstMedia('mobile_creative')?->file_name)->toBe('mobile.jpg');
});

it('replaces the desktop creative without disturbing the mobile one, since each collection holds a single file', function (): void {
    Storage::fake('public');

    $banner = mediaTestBanner();

    $banner->addMedia(UploadedFile::fake()->image('desktop-v1.jpg'))->toMediaCollection('desktop_creative');
    $banner->addMedia(UploadedFile::fake()->image('mobile.jpg'))->toMediaCollection('mobile_creative');

    // singleFile() collections replace their prior occupant on the next add
    // — this is the mechanism, not an accident of upload order.
    $banner->addMedia(UploadedFile::fake()->image('desktop-v2.jpg'))->toMediaCollection('desktop_creative');

    $reloaded = Banner::query()->findOrFail($banner->id);

    expect($reloaded->getMedia('desktop_creative'))->toHaveCount(1)
        ->and($reloaded->getFirstMedia('desktop_creative')?->file_name)->toBe('desktop-v2.jpg')
        ->and($reloaded->getMedia('mobile_creative'))->toHaveCount(1)
        ->and($reloaded->getFirstMedia('mobile_creative')?->file_name)->toBe('mobile.jpg');
});
