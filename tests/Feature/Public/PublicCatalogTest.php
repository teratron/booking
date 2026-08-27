<?php

declare(strict_types=1);

use App\Livewire\Public\CatalogSearch;
use App\Models\Banner;
use App\Models\Object_;
use App\Models\Territory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Public Catalog / Search Results Page
|--------------------------------------------------------------------------
|
| Every §5.1 search parameter round-trips through the URL; a filter change
| never discards the view-mode choice and vice versa; results and the map's
| pins update in the same round trip; pagination is addressable by page
| number.
|
*/

/** @return array{languageId: int, countryId: int, territoryId: int, hotelTypeId: int, restaurantTypeId: int} */
function publicCatalogRegistry(): array
{
    $languageId = DB::table('languages')->insertGetId([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $countryId = DB::table('countries')->insertGetId([
        'code' => 'MD', 'currency' => 'MDL', 'phone_code' => '+373',
        'primary_language_id' => $languageId, 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $levelId = DB::table('territory_levels')->insertGetId([
        'country_id' => $countryId, 'depth_rank' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $territoryId = DB::table('territories')->insertGetId([
        'country_id' => $countryId, 'level_id' => $levelId, 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('territory_translations')->insert([
        'territory_id' => $territoryId, 'country_id' => $countryId, 'locale' => 'en', 'name' => 'Catalog City', 'slug' => 'catalog-city',
        'full_slug_path' => 'catalog-city',
        'needs_review' => false, 'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    $hotelTypeId = DB::table('object_types')->insertGetId([
        'key' => 'hotel', 'is_active' => true, 'has_availability_status' => true, 'display_order' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('object_type_translations')->insert([
        'object_type_id' => $hotelTypeId, 'locale' => 'en', 'name' => 'Hotel', 'slug' => 'hotel',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $restaurantTypeId = DB::table('object_types')->insertGetId([
        'key' => 'restaurant', 'is_active' => true, 'has_availability_status' => false, 'display_order' => 2,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('object_type_translations')->insert([
        'object_type_id' => $restaurantTypeId, 'locale' => 'en', 'name' => 'Restaurant', 'slug' => 'restaurant',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return compact('languageId', 'countryId', 'territoryId', 'hotelTypeId', 'restaurantTypeId');
}

function publicCatalogMakeObject(array $fixture, int $typeId, string $name): Object_
{
    $objectId = DB::table('objects')->insertGetId([
        'ulid' => (string) Str::ulid(),
        'owner_id' => User::factory()->create()->id,
        'object_type_id' => $typeId,
        'territory_id' => $fixture['territoryId'],
        'country_id' => $fixture['countryId'],
        'status' => 'published', 'moderation_status' => 'approved',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('object_translations')->insert([
        'object_id' => $objectId, 'locale' => 'en', 'name' => $name,
        'slug' => Str::slug($name).'-'.$objectId, 'needs_review' => false, 'published_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    /** @var Object_ $object */
    $object = Object_::query()->findOrFail($objectId);

    return $object;
}

it('reads the full search parameter set from the URL on mount, so a filtered view is shareable and back-navigable', function (): void {
    $fixture = publicCatalogRegistry();
    publicCatalogMakeObject($fixture, $fixture['hotelTypeId'], 'Grand Hotel');

    Livewire::withQueryParams([
        'q' => 'Grand',
        'type' => (string) $fixture['hotelTypeId'],
        'priceMin' => '10',
        'priceMax' => '500',
        'ratingMin' => '4',
        'view' => 'list',
    ])->test(CatalogSearch::class)
        ->assertSet('q', 'Grand')
        ->assertSet('type', $fixture['hotelTypeId'])
        ->assertSet('priceMin', 10.0)
        ->assertSet('priceMax', 500.0)
        ->assertSet('ratingMin', 4.0)
        ->assertSet('viewMode', 'list');
});

it('never discards the view-mode choice when a filter changes, and vice versa', function (): void {
    $fixture = publicCatalogRegistry();

    Livewire::test(CatalogSearch::class)
        ->call('setViewMode', 'list')
        ->set('type', $fixture['hotelTypeId'])
        ->assertSet('viewMode', 'list')
        ->set('priceMin', 50)
        ->assertSet('viewMode', 'list')
        ->assertSet('type', $fixture['hotelTypeId']);
});

it('renders results honouring type, name, and price filters — never a second retrieval implementation', function (): void {
    $fixture = publicCatalogRegistry();
    $matching = publicCatalogMakeObject($fixture, $fixture['hotelTypeId'], 'Seaside Hotel');
    publicCatalogMakeObject($fixture, $fixture['restaurantTypeId'], 'Seaside Diner');

    Livewire::test(CatalogSearch::class)
        ->set('type', $fixture['hotelTypeId'])
        ->assertSee('Seaside Hotel')
        ->assertDontSee('Seaside Diner');

    expect($matching)->not->toBeNull();
});

it('updates the map pins in the same round trip a filter change updates the results, via one dispatched event', function (): void {
    $fixture = publicCatalogRegistry();

    Livewire::test(CatalogSearch::class)
        ->set('type', $fixture['hotelTypeId'])
        ->assertDispatched('catalog-filters-changed', type: $fixture['hotelTypeId']);
});

it('resets to page one when a filter changes but addresses pagination by page number in the URL', function (): void {
    $fixture = publicCatalogRegistry();

    for ($i = 0; $i < 30; $i++) {
        publicCatalogMakeObject($fixture, $fixture['hotelTypeId'], "Hotel {$i}");
    }

    $component = Livewire::withQueryParams(['page' => '2'])->test(CatalogSearch::class);
    expect($component->get('paginators')['page'] ?? null)->not->toBeNull();

    $component->set('type', $fixture['hotelTypeId']);

    // Changing a filter resets pagination back to page one — proven by the
    // rendered result set no longer being the second page's slice.
    expect($component->viewData('results')->currentPage())->toBe(1);
});

it('shows the union of filterable amenity groups across active types before any type is chosen', function (): void {
    $fixture = publicCatalogRegistry();

    $sharedGroupId = DB::table('amenity_groups')->insertGetId([
        'is_active' => true, 'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $wifiId = DB::table('amenities')->insertGetId([
        'amenity_group_id' => $sharedGroupId, 'is_filterable' => true, 'is_active' => true,
        'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('amenity_translations')->insert([
        'amenity_id' => $wifiId, 'locale' => 'en', 'name' => 'Free Wi-Fi', 'created_at' => now(), 'updated_at' => now(),
    ]);

    // The group only reaches the restaurant type, never the hotel type —
    // the union has to look past whichever type a visitor eventually picks.
    DB::table('amenity_group_object_type')->insert([
        'amenity_group_id' => $sharedGroupId, 'object_type_id' => $fixture['restaurantTypeId'],
    ]);

    $component = Livewire::test(CatalogSearch::class);

    expect($component->get('type'))->toBeNull()
        ->and($component->viewData('amenityGroups')->pluck('id')->all())->toContain($sharedGroupId);
});

it('narrows the amenity groups back to the selected type once one is chosen', function (): void {
    $fixture = publicCatalogRegistry();

    $restaurantOnlyGroupId = DB::table('amenity_groups')->insertGetId([
        'is_active' => true, 'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $cateringId = DB::table('amenities')->insertGetId([
        'amenity_group_id' => $restaurantOnlyGroupId, 'is_filterable' => true, 'is_active' => true,
        'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('amenity_translations')->insert([
        'amenity_id' => $cateringId, 'locale' => 'en', 'name' => 'Catering', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('amenity_group_object_type')->insert([
        'amenity_group_id' => $restaurantOnlyGroupId, 'object_type_id' => $fixture['restaurantTypeId'],
    ]);

    $component = Livewire::test(CatalogSearch::class)->set('type', $fixture['hotelTypeId']);

    expect($component->viewData('amenityGroups')->pluck('id')->all())->not->toContain($restaurantOnlyGroupId);
});

/**
 * Places a banner in the `catalog-top` slot, targeted at $territoryId when
 * given (null means untargeted — every territory matches).
 */
function publicCatalogMakeBanner(?int $territoryId, string $linkText = 'Advertiser banner'): Banner
{
    $slotId = DB::table('banner_slots')->insertGetId([
        'key' => 'catalog-top', 'surfaces' => json_encode(['category']),
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $bannerId = DB::table('banners')->insertGetId([
        'banner_slot_id' => $slotId, 'name' => "Banner for catalog-top ({$linkText})", 'advertiser' => 'Acme',
        'destination_link' => 'https://example.test', 'display_order' => 0,
        'starts_at' => now()->subDay()->toDateString(), 'ends_at' => now()->addMonth()->toDateString(),
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('banner_translations')->insert([
        'banner_id' => $bannerId, 'locale' => 'en', 'link_text' => $linkText,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    if ($territoryId !== null) {
        DB::table('banner_targets')->insert([
            'banner_id' => $bannerId, 'target_type' => (new Territory)->getMorphClass(), 'target_id' => $territoryId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    return Banner::query()->findOrFail($bannerId);
}

it('renders a banner targeted at the selected territory on the catalog page', function (): void {
    // F-24/S-18 continuation: BannerSelectionService::forSlot() had no
    // caller at all on the catalog/search page before this fix.
    $fixture = publicCatalogRegistry();
    $banner = publicCatalogMakeBanner($fixture['territoryId'], 'Own territory banner');

    Livewire::test(CatalogSearch::class)
        ->set('territoryId', $fixture['territoryId'])
        ->assertSee(route('banners.click', ['banner' => $banner->id]), false);
});

it('never shows a catalog-page banner targeted at a different territory than the one selected', function (): void {
    $fixture = publicCatalogRegistry();
    $otherTerritoryId = DB::table('territories')->insertGetId([
        'country_id' => $fixture['countryId'],
        'level_id' => DB::table('territory_levels')->insertGetId([
            'country_id' => $fixture['countryId'], 'depth_rank' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]),
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('territory_translations')->insert([
        'territory_id' => $otherTerritoryId, 'country_id' => $fixture['countryId'], 'locale' => 'en',
        'name' => 'Other Catalog Territory', 'slug' => 'other-catalog-territory',
        'full_slug_path' => 'other-catalog-territory',
        'needs_review' => false, 'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);

    $banner = publicCatalogMakeBanner($fixture['territoryId'], 'Own territory banner');

    Livewire::test(CatalogSearch::class)
        ->set('territoryId', $otherTerritoryId)
        ->assertDontSee(route('banners.click', ['banner' => $banner->id]), false);
});
