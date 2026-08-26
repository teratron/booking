<?php

declare(strict_types=1);

use App\Jobs\CaptureStatEventJob;
use App\Models\Object_;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Object Card Component
|--------------------------------------------------------------------------
|
| The single result-card component every public listing surface renders.
| Card geometry never changes between tiers — only the border colour and
| the ribbon badge do — and a card-view event fires exactly once per
| rendered card, never once per page.
|
*/

/** @return array{countryId: int, territoryId: int, accommodationTypeId: int, diningTypeId: int} */
function objectCardGeography(): array
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
        'country_id' => $countryId, 'level_id' => $levelId, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('territory_translations')->insert([
        'territory_id' => $territoryId, 'country_id' => $countryId, 'locale' => 'en', 'name' => 'Seaside City', 'slug' => 'seaside-city',
        'full_slug_path' => 'seaside-city',
        'needs_review' => false, 'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    $accommodationTypeId = DB::table('object_types')->insertGetId([
        'key' => 'accommodation', 'is_active' => true, 'has_availability_status' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $diningTypeId = DB::table('object_types')->insertGetId([
        'key' => 'dining', 'is_active' => true, 'has_availability_status' => false,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return compact('countryId', 'territoryId', 'accommodationTypeId', 'diningTypeId');
}

/** @param  array<string, mixed>  $overrides */
function objectCardMakeObject(array $fixture, int $typeId, string $name, array $overrides = []): Object_
{
    $objectId = DB::table('objects')->insertGetId(array_merge([
        'ulid' => (string) Str::ulid(),
        'owner_id' => User::factory()->create()->id,
        'object_type_id' => $typeId,
        'territory_id' => $fixture['territoryId'],
        'country_id' => $fixture['countryId'],
        'status' => 'published', 'moderation_status' => 'approved',
        'availability_status' => 'available',
        'created_at' => now(), 'updated_at' => now(),
    ], $overrides));

    DB::table('object_translations')->insert([
        'object_id' => $objectId, 'locale' => 'en', 'name' => $name,
        'short_description' => 'A short teaser description for the card.',
        'slug' => Str::slug($name).'-'.$objectId, 'needs_review' => false, 'published_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    /** @var Object_ $object */
    $object = Object_::query()->findOrFail($objectId);

    return $object;
}

function objectCardMakePlacement(Object_ $object, int $tierRank, string $badgeText = 'VIP'): void
{
    $tierId = DB::table('placement_tiers')->insertGetId([
        'rank' => $tierRank, 'border_colour' => '#f8bb44', 'badge_colour' => '#f8bb44',
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('placement_tier_translations')->insert([
        'placement_tier_id' => $tierId, 'locale' => 'en', 'label' => $badgeText, 'badge_text' => $badgeText,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $packageId = DB::table('placement_packages')->insertGetId([
        'placement_tier_id' => $tierId, 'price' => 10, 'currency' => 'EUR',
        'validity_days' => 30, 'bump_allowed' => false, 'is_active' => true,
        'display_order' => $tierRank, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('object_placements')->insert([
        'object_id' => $object->id, 'placement_package_id' => $packageId,
        'starts_at' => now()->subDays(1)->toDateString(), 'ends_at' => now()->addDays(30)->toDateString(),
        'internal_priority' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

it('renders every field the card contract names: cover, name, settlement, description, key services, rating, view count, details action, and contact actions', function (): void {
    Storage::fake('public');
    $fixture = objectCardGeography();
    $object = objectCardMakeObject($fixture, $fixture['accommodationTypeId'], 'Seaside Villa');

    $object->addMedia(UploadedFile::fake()->image('cover.jpg', 800, 600))->toMediaCollection('photos');

    $groupId = DB::table('amenity_groups')->insertGetId(['is_active' => true, 'display_order' => 0, 'created_at' => now(), 'updated_at' => now()]);
    $amenityId = DB::table('amenities')->insertGetId([
        'amenity_group_id' => $groupId, 'is_filterable' => true, 'is_active' => true,
        'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('amenity_translations')->insert([
        'amenity_id' => $amenityId, 'locale' => 'en', 'name' => 'Free Wi-Fi',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('amenity_object')->insert(['amenity_id' => $amenityId, 'object_id' => $object->id]);

    DB::table('reviews')->insert([
        'object_id' => $object->id, 'rating' => 5, 'body' => 'Great stay.', 'status' => 'published',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    DB::table('stat_dailies')->insert([
        'date' => now()->toDateString(), 'subject_type' => Object_::class, 'subject_id' => $object->id,
        'kind' => 'object_page_view', 'count' => 42, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $channelTypeId = DB::table('contact_channel_types')->insertGetId([
        'key' => 'phone', 'link_template' => 'tel:{value}', 'is_active' => true,
        'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('contact_channel_type_translations')->insert([
        'contact_channel_type_id' => $channelTypeId, 'locale' => 'en', 'display_name' => 'Phone',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $channelId = DB::table('contact_channels')->insertGetId([
        'object_id' => $object->id, 'contact_channel_type_id' => $channelTypeId,
        'raw_value' => '+37360000000', 'is_active' => true, 'display_order' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $view = $this->blade('<x-object-card :object="$object" />', ['object' => $object->fresh()]);

    // The card's own contact action routes through the click-capture
    // redirect rather than the raw tel: link, so the click itself is
    // always measured before the visitor leaves the portal.
    $clickUrl = route('public.objects.contact.click', ['lang' => 'en', 'object' => $object->id, 'channel' => $channelId]);

    $view->assertSee('Seaside Villa')
        ->assertSee('Seaside City')
        ->assertSee('A short teaser description for the card.')
        ->assertSee('Free Wi-Fi', escape: false)
        ->assertSee('5.0 / 5')
        ->assertSee('42', escape: false)
        ->assertSee($clickUrl, escape: false);
});

it('renders the availability badge for a type that declares it, never for one that does not', function (): void {
    $fixture = objectCardGeography();
    $accommodation = objectCardMakeObject($fixture, $fixture['accommodationTypeId'], 'Tracked Villa');
    $dining = objectCardMakeObject($fixture, $fixture['diningTypeId'], 'Untracked Diner');

    $accommodationView = $this->blade('<x-object-card :object="$object" />', ['object' => $accommodation->fresh()]);
    $diningView = $this->blade('<x-object-card :object="$object" />', ['object' => $dining->fresh()]);

    $accommodationView->assertSee(__('public.catalog.card.availability.available'));
    $diningView->assertDontSee(__('public.catalog.card.availability.available'))
        ->assertDontSee(__('public.catalog.card.availability.unavailable'))
        ->assertDontSee(__('public.catalog.card.availability.unspecified'));
});

it('renders the tier badge and border colour, keeping card geometry identical across tiers', function (): void {
    $fixture = objectCardGeography();
    $vip = objectCardMakeObject($fixture, $fixture['accommodationTypeId'], 'VIP Villa');
    $unranked = objectCardMakeObject($fixture, $fixture['accommodationTypeId'], 'Unranked Villa');
    objectCardMakePlacement($vip, 1, 'VIP');

    $vipHtml = (string) $this->blade('<x-object-card :object="$object" />', ['object' => $vip->fresh()]);
    $unrankedHtml = (string) $this->blade('<x-object-card :object="$object" />', ['object' => $unranked->fresh()]);

    expect($vipHtml)->toContain('VIP')
        ->and($vipHtml)->toContain('#f8bb44');

    // Strip the one attribute that is allowed to differ (the dynamic border
    // colour) before comparing — everything else about the outer
    // structure must be byte-identical regardless of tier.
    $stripBorder = fn (string $html): string => preg_replace('/style="border: 2px solid [^"]*"/', 'style="border"', $html);

    $vipStructure = preg_replace('/<span[^>]*style="background-color[^<]*<\/span>/', '', $stripBorder($vipHtml));
    $unrankedStructure = $stripBorder($unrankedHtml);

    expect(substr_count((string) $vipStructure, 'class="flex overflow-hidden rounded-lg bg-surface-muted shadow-card w-full flex-col sm:flex-row"'))
        ->toBe(1)
        ->and(substr_count((string) $unrankedStructure, 'class="flex overflow-hidden rounded-lg bg-surface-muted shadow-card w-full flex-col sm:flex-row"'))
        ->toBe(1);
});

it('renders the row geometry by default, matching the Figma source exactly', function (): void {
    $fixture = objectCardGeography();
    $object = objectCardMakeObject($fixture, $fixture['accommodationTypeId'], 'Default Geometry Villa');

    $html = (string) $this->blade('<x-object-card :object="$object" />', ['object' => $object->fresh()]);

    expect($html)->toContain('sm:flex-row')
        ->and($html)->toContain('sm:w-72')
        ->and($html)->not->toContain('sm:grid-cols');
});

it('renders the tile geometry — image over content, no viewport-conditional row switch — when placed in a grid', function (): void {
    // F-09/S-09: the row geometry's sm:flex-row activated at any viewport
    // width regardless of how narrow the actual grid cell was — Tailwind
    // breakpoints key off the viewport, not the container — clipping
    // titles and cutting off the details button in every grid placement
    // (the home page's rails, the catalog's own tile toggle). This variant
    // exists specifically so a caller placing the card in a multi-column
    // grid can say so.
    $fixture = objectCardGeography();
    $object = objectCardMakeObject($fixture, $fixture['accommodationTypeId'], 'Tile Geometry Villa');

    $html = (string) $this->blade('<x-object-card variant="tile" :object="$object" />', ['object' => $object->fresh()]);

    expect($html)->not->toContain('sm:flex-row')
        ->and($html)->not->toContain('sm:w-72')
        ->and($html)->toContain('Tile Geometry Villa');
});

it('captures exactly one object_card_view event per rendered card, never once per page', function (): void {
    Bus::fake();
    $fixture = objectCardGeography();
    $objectA = objectCardMakeObject($fixture, $fixture['accommodationTypeId'], 'Card A');
    $objectB = objectCardMakeObject($fixture, $fixture['accommodationTypeId'], 'Card B');

    $this->blade('<x-object-card :object="$object" />', ['object' => $objectA->fresh()]);

    Bus::assertDispatchedTimes(CaptureStatEventJob::class, 1);
    Bus::assertDispatched(CaptureStatEventJob::class, function (CaptureStatEventJob $job) use ($objectA): bool {
        $kind = (new ReflectionProperty($job, 'kind'))->getValue($job);
        $subjectId = (new ReflectionProperty($job, 'subjectId'))->getValue($job);

        return $kind === 'object_card_view' && $subjectId === $objectA->id;
    });

    // A second card on the same page renders a second, independent event —
    // capture is per card instance, not a page-level side effect.
    $this->blade('<x-object-card :object="$object" />', ['object' => $objectB->fresh()]);

    Bus::assertDispatchedTimes(CaptureStatEventJob::class, 2);
});

it("resolves the room's own price when no price is attached directly to the object", function (): void {
    $fixture = objectCardGeography();
    $object = objectCardMakeObject($fixture, $fixture['accommodationTypeId'], 'Room Priced Hotel');
    $roomId = DB::table('rooms')->insertGetId([
        'object_id' => $object->id, 'display_order' => 0, 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('prices')->insert([
        'priceable_type' => Room::class, 'priceable_id' => $roomId,
        'calculation_unit' => 'per_night', 'amount' => 75, 'currency' => 'EUR',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $view = $this->blade('<x-object-card :object="$object" />', ['object' => $object->fresh()]);

    $view->assertSee('75', escape: false)->assertSee('EUR', escape: false);
});
