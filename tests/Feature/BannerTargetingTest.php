<?php

declare(strict_types=1);

use App\Models\Banner;
use App\Models\Language;
use App\Models\ObjectType;
use App\Models\Territory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Banner Targeting
|--------------------------------------------------------------------------
|
| One polymorphic pivot unifies territory, category, and language targeting.
| An untargeted dimension means "eligible everywhere" — no pivot rows, not a
| row meaning "none". Selection (walking a targeted ancestor territory down
| to a descendant) is a later, separate concern; here the pivot itself must
| simply round-trip correctly through each of the three relations.
|
*/

function targetingBannerSlot(): int
{
    return DB::table('banner_slots')->insertGetId([
        'key' => 'targeting_probe', 'surfaces' => json_encode(['home']),
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function targetingBanner(?int $slotId = null): Banner
{
    $bannerId = DB::table('banners')->insertGetId([
        'banner_slot_id' => $slotId ?? targetingBannerSlot(),
        'name' => 'Targeting probe banner', 'advertiser' => 'Acme',
        'destination_link' => 'https://example.test',
        'starts_at' => now()->toDateString(), 'ends_at' => now()->addMonth()->toDateString(),
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return Banner::query()->findOrFail($bannerId);
}

function targetingCountryAndRegion(): array
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
    $levelId = DB::table('territory_levels')->insertGetId([
        'country_id' => $countryId, 'depth_rank' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $regionId = DB::table('territories')->insertGetId([
        'country_id' => $countryId, 'level_id' => $levelId, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return compact('languageId', 'countryId', 'levelId', 'regionId');
}

it('leaves every targeting dimension empty by default, meaning eligible everywhere', function (): void {
    $banner = targetingBanner();

    expect($banner->territories()->count())->toBe(0)
        ->and($banner->categories()->count())->toBe(0)
        ->and($banner->targetLanguages()->count())->toBe(0)
        ->and(DB::table('banner_targets')->where('banner_id', $banner->id)->count())->toBe(0);
});

it('round-trips a territory attached to a banner through the territories() relation', function (): void {
    $geo = targetingCountryAndRegion();
    $banner = targetingBanner();

    $banner->territories()->attach($geo['regionId']);

    $stored = DB::table('banner_targets')
        ->where('banner_id', $banner->id)
        ->where('target_type', (new Territory)->getMorphClass())
        ->first();

    expect($stored)->not->toBeNull()
        ->and($stored->target_id)->toBe($geo['regionId']);

    $reloaded = Banner::query()->findOrFail($banner->id);

    expect($reloaded->territories()->pluck('territories.id')->all())->toBe([$geo['regionId']])
        ->and($reloaded->categories()->count())->toBe(0)
        ->and($reloaded->targetLanguages()->count())->toBe(0);
});

it('targets any combination of territory, category, and language at once, each independently', function (): void {
    $geo = targetingCountryAndRegion();
    $objectTypeId = DB::table('object_types')->insertGetId([
        'key' => 'targeting_probe_type', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $secondLanguageId = DB::table('languages')->insertGetId([
        'code' => 'ru', 'short_label' => 'RU', 'is_active' => true, 'is_primary' => false,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $banner = targetingBanner();

    $banner->territories()->attach($geo['regionId']);
    $banner->categories()->attach($objectTypeId);
    $banner->targetLanguages()->attach([$geo['languageId'], $secondLanguageId]);

    $reloaded = Banner::query()->findOrFail($banner->id);

    expect($reloaded->territories()->pluck('territories.id')->all())->toBe([$geo['regionId']])
        ->and($reloaded->categories()->pluck('object_types.id')->all())->toBe([$objectTypeId])
        ->and($reloaded->targetLanguages()->pluck('languages.id')->all())->toEqualCanonicalizing([$geo['languageId'], $secondLanguageId]);

    // The pivot itself must distinguish the three dimensions by target_type,
    // not merely by which banner_id rows exist.
    $storedTypes = DB::table('banner_targets')->where('banner_id', $banner->id)->pluck('target_type')->unique()->sort()->values()->all();

    expect($storedTypes)->toEqualCanonicalizing([
        (new Territory)->getMorphClass(),
        (new ObjectType)->getMorphClass(),
        (new Language)->getMorphClass(),
    ]);
});

it('leaves the other two dimensions untargeted when only one is set', function (): void {
    $geo = targetingCountryAndRegion();
    $banner = targetingBanner();

    $banner->categories()->attach(
        DB::table('object_types')->insertGetId([
            'key' => 'only_category_probe', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]),
    );

    $reloaded = Banner::query()->findOrFail($banner->id);

    expect($reloaded->categories()->count())->toBe(1)
        ->and($reloaded->territories()->count())->toBe(0)
        ->and($reloaded->targetLanguages()->count())->toBe(0);
});
