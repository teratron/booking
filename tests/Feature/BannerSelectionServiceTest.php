<?php

declare(strict_types=1);

use App\Jobs\CaptureStatEventJob;
use App\Models\Banner;
use App\Models\BannerSlot;
use App\Models\Language;
use App\Services\Advertising\BannerSelectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Banner Selection Pipeline
|--------------------------------------------------------------------------
|
| BannerSelectionService::forSlot() runs a fixed pipeline — slot, schedule,
| language, category, territory — and ranks whatever survives by territory
| specificity, then display order, then rotation among exact ties. Every
| filter is independent of every other: a banner failing one step is
| excluded regardless of how well it would have scored on a later one.
|
*/

/**
 * A country with one region and, nested under it, one city — enough depth to
 * prove the territory walk is transitive (city inherits a region-level
 * target) and directional (a sibling region never matches).
 *
 * @return array{languageId: int, countryId: int, regionId: int, cityId: int, siblingRegionId: int}
 */
function selectionGeography(): array
{
    $languageId = DB::table('languages')->insertGetId([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $countryId = DB::table('countries')->insertGetId([
        'code' => 'MD', 'currency' => 'MDL', 'phone_code' => '+373',
        'primary_language_id' => $languageId, 'is_active' => true, 'display_order' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $regionLevelId = DB::table('territory_levels')->insertGetId([
        'country_id' => $countryId, 'depth_rank' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $cityLevelId = DB::table('territory_levels')->insertGetId([
        'country_id' => $countryId, 'depth_rank' => 2, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $regionId = DB::table('territories')->insertGetId([
        'country_id' => $countryId, 'level_id' => $regionLevelId, 'parent_id' => null,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $cityId = DB::table('territories')->insertGetId([
        'country_id' => $countryId, 'level_id' => $cityLevelId, 'parent_id' => $regionId,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $siblingRegionId = DB::table('territories')->insertGetId([
        'country_id' => $countryId, 'level_id' => $regionLevelId, 'parent_id' => null,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return compact('languageId', 'countryId', 'regionId', 'cityId', 'siblingRegionId');
}

function selectionSlot(string $key = 'selection_probe'): BannerSlot
{
    $id = DB::table('banner_slots')->insertGetId([
        'key' => $key, 'surfaces' => json_encode(['home']),
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return BannerSlot::query()->findOrFail($id);
}

/** @param  array<string, mixed>  $overrides */
function selectionBanner(int $slotId, array $overrides = []): Banner
{
    $id = DB::table('banners')->insertGetId(array_merge([
        'banner_slot_id' => $slotId,
        'name' => 'Selection probe banner',
        'advertiser' => 'Acme',
        'destination_link' => 'https://example.test/'.uniqid('', true),
        'display_order' => 0,
        'starts_at' => now()->subDay()->toDateString(),
        'ends_at' => now()->addMonth()->toDateString(),
        'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ], $overrides));

    return Banner::query()->findOrFail($id);
}

it('excludes a schedule-expired banner even though it is the most specific territory match', function (): void {
    $geo = selectionGeography();
    $slot = selectionSlot();

    $expired = selectionBanner($slot->id, [
        'display_order' => 0,
        'ends_at' => now()->subDay()->toDateString(),
    ]);
    $expired->territories()->attach($geo['cityId']);

    $valid = selectionBanner($slot->id, ['display_order' => 5]);
    $valid->territories()->attach($geo['regionId']);

    $selected = app(BannerSelectionService::class)->forSlot($slot, ['territory' => $geo['cityId']]);

    expect($selected)->not->toBeNull()
        ->and($selected->id)->toBe($valid->id);
});

it('excludes a banner deactivated with is_active = false regardless of how well it would otherwise match', function (): void {
    $geo = selectionGeography();
    $slot = selectionSlot();

    $inactive = selectionBanner($slot->id, ['is_active' => false]);
    $inactive->territories()->attach($geo['cityId']);

    $selected = app(BannerSelectionService::class)->forSlot($slot, ['territory' => $geo['cityId']]);

    expect($selected)->toBeNull();
});

it('collapses the slot to null when zero banners survive the pipeline', function (): void {
    $slot = selectionSlot();

    selectionBanner($slot->id, ['ends_at' => now()->subDay()->toDateString()]);

    $selected = app(BannerSelectionService::class)->forSlot($slot);

    expect($selected)->toBeNull();
});

it('returns null for a slot key that does not exist, rather than throwing', function (): void {
    $selected = app(BannerSelectionService::class)->forSlot('does-not-exist');

    expect($selected)->toBeNull();
});

it('ranks the banner matching the requested territory exactly above one matching only through an ancestor, regardless of display order', function (): void {
    $geo = selectionGeography();
    $slot = selectionSlot();

    $exactMatch = selectionBanner($slot->id, ['display_order' => 100]);
    $exactMatch->territories()->attach($geo['cityId']);

    $ancestorMatch = selectionBanner($slot->id, ['display_order' => 0]);
    $ancestorMatch->territories()->attach($geo['regionId']);

    $selected = app(BannerSelectionService::class)->forSlot($slot, ['territory' => $geo['cityId']]);

    expect($selected)->not->toBeNull()
        ->and($selected->id)->toBe($exactMatch->id);
});

it('excludes a banner targeting an unrelated sibling territory, proving the walk is strictly upward through ancestors', function (): void {
    $geo = selectionGeography();
    $slot = selectionSlot();

    $sibling = selectionBanner($slot->id);
    $sibling->territories()->attach($geo['siblingRegionId']);

    $selected = app(BannerSelectionService::class)->forSlot($slot, ['territory' => $geo['cityId']]);

    expect($selected)->toBeNull();
});

it('breaks a territory-specificity tie by display order', function (): void {
    $geo = selectionGeography();
    $slot = selectionSlot();

    $worse = selectionBanner($slot->id, ['display_order' => 10]);
    $worse->territories()->attach($geo['cityId']);

    $better = selectionBanner($slot->id, ['display_order' => 1]);
    $better->territories()->attach($geo['cityId']);

    $selected = app(BannerSelectionService::class)->forSlot($slot, ['territory' => $geo['cityId']]);

    expect($selected)->not->toBeNull()
        ->and($selected->id)->toBe($better->id);
});

it('rotates among banners tied on both specificity and display order rather than always serving the same one', function (): void {
    $slot = selectionSlot();

    $tied = [
        selectionBanner($slot->id, ['display_order' => 1])->id,
        selectionBanner($slot->id, ['display_order' => 1])->id,
        selectionBanner($slot->id, ['display_order' => 1])->id,
    ];

    $service = app(BannerSelectionService::class);

    $served = [];
    for ($i = 0; $i < 3; $i++) {
        $winner = $service->forSlot($slot);
        $served[] = $winner?->id;
    }

    sort($tied);
    sort($served);

    // Three calls, three tied banners: rotation must have visited every one
    // of them exactly once rather than repeating a single winner.
    expect($served)->toBe($tied);
});

it('is eligible for every language when the banner carries no language target', function (): void {
    $slot = selectionSlot();
    $banner = selectionBanner($slot->id);

    $selected = app(BannerSelectionService::class)->forSlot($slot, ['language' => 'en']);

    expect($selected?->id)->toBe($banner->id);
});

it('excludes a language-targeted banner when the requested language is not in its target set', function (): void {
    $geo = selectionGeography();
    $slot = selectionSlot();
    $banner = selectionBanner($slot->id);
    $banner->targetLanguages()->attach($geo['languageId']);

    $ruLanguageId = DB::table('languages')->insertGetId([
        'code' => 'ru', 'short_label' => 'RU', 'is_active' => true, 'is_primary' => false,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $selected = app(BannerSelectionService::class)->forSlot($slot, [
        'language' => Language::query()->findOrFail($ruLanguageId),
    ]);

    expect($selected)->toBeNull();
});

it('includes a language-targeted banner when the requested language is in its target set', function (): void {
    $geo = selectionGeography();
    $slot = selectionSlot();
    $banner = selectionBanner($slot->id);
    $banner->targetLanguages()->attach($geo['languageId']);

    $selected = app(BannerSelectionService::class)->forSlot($slot, ['language' => 'en']);

    expect($selected?->id)->toBe($banner->id);
});

it('does not filter by category when the page context supplies none, even for a category-targeted banner', function (): void {
    $slot = selectionSlot();
    $banner = selectionBanner($slot->id);
    $typeId = DB::table('object_types')->insertGetId([
        'key' => 'selection_probe_type', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $banner->categories()->attach($typeId);

    $selected = app(BannerSelectionService::class)->forSlot($slot);

    expect($selected?->id)->toBe($banner->id);
});

it('excludes a category-targeted banner when the page category is not in its target set', function (): void {
    $slot = selectionSlot();
    $banner = selectionBanner($slot->id);
    $matchingTypeId = DB::table('object_types')->insertGetId([
        'key' => 'selection_probe_match', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $otherTypeId = DB::table('object_types')->insertGetId([
        'key' => 'selection_probe_other', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $banner->categories()->attach($matchingTypeId);

    $selected = app(BannerSelectionService::class)->forSlot($slot, ['category' => $otherTypeId]);

    expect($selected)->toBeNull();
});

it('records exactly one banner_impression event for the served banner through the real capture path', function (): void {
    Queue::fake();

    $slot = selectionSlot();
    $banner = selectionBanner($slot->id);

    $selected = app(BannerSelectionService::class)->forSlot($slot);

    expect($selected?->id)->toBe($banner->id);

    Queue::assertPushed(CaptureStatEventJob::class, 1);
});

it('never records an impression when the slot collapses', function (): void {
    Queue::fake();

    $slot = selectionSlot();
    selectionBanner($slot->id, ['ends_at' => now()->subDay()->toDateString()]);

    $selected = app(BannerSelectionService::class)->forSlot($slot);

    expect($selected)->toBeNull();

    Queue::assertNotPushed(CaptureStatEventJob::class);
});

it('invalidates the cached selection when a banner is created, updated, or deleted', function (): void {
    $slot = selectionSlot();

    Cache::tags(["slot:{$slot->id}"])->put('probe', 'stale-on-create', 3600);
    expect(Cache::tags(["slot:{$slot->id}"])->get('probe'))->toBe('stale-on-create');

    $banner = Banner::query()->create([
        'banner_slot_id' => $slot->id,
        'name' => 'Freshly created',
        'advertiser' => 'Acme',
        'destination_link' => 'https://example.test',
        'display_order' => 0,
        'starts_at' => now()->subDay()->toDateString(),
        'ends_at' => now()->addMonth()->toDateString(),
        'is_active' => true,
    ]);

    expect(Cache::tags(["slot:{$slot->id}"])->get('probe'))->toBeNull();

    Cache::tags(["slot:{$slot->id}"])->put('probe', 'stale-on-update', 3600);
    $banner->update(['is_active' => false]);

    expect(Cache::tags(["slot:{$slot->id}"])->get('probe'))->toBeNull();

    Cache::tags(["slot:{$slot->id}"])->put('probe', 'stale-on-delete', 3600);
    $banner->delete();

    expect(Cache::tags(["slot:{$slot->id}"])->get('probe'))->toBeNull();
});

it('invalidates both the origin and destination slot when a banner is reassigned to a different slot', function (): void {
    $slotA = selectionSlot('selection_slot_a');
    $slotB = selectionSlot('selection_slot_b');

    $banner = selectionBanner($slotA->id);
    $banner = Banner::query()->findOrFail($banner->id);

    Cache::tags(["slot:{$slotA->id}"])->put('probe', 'stale-a', 3600);
    Cache::tags(["slot:{$slotB->id}"])->put('probe', 'stale-b', 3600);

    $banner->update(['banner_slot_id' => $slotB->id]);

    expect(Cache::tags(["slot:{$slotA->id}"])->get('probe'))->toBeNull()
        ->and(Cache::tags(["slot:{$slotB->id}"])->get('probe'))->toBeNull();
});

it('increments the winning banner\'s lifetime impressions counter atomically alongside the event', function (): void {
    Queue::fake();

    $slot = selectionSlot();
    $banner = selectionBanner($slot->id);

    expect($banner->impressions)->toBe(0);

    app(BannerSelectionService::class)->forSlot($slot);
    app(BannerSelectionService::class)->forSlot($slot);

    expect($banner->fresh()->impressions)->toBe(2);
});

it('never increments impressions for a banner the pipeline did not select', function (): void {
    Queue::fake();

    $slot = selectionSlot();
    $loser = selectionBanner($slot->id, ['is_active' => false]);

    app(BannerSelectionService::class)->forSlot($slot);

    expect($loser->fresh()->impressions)->toBe(0);
});
