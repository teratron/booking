<?php

declare(strict_types=1);

use App\Jobs\CaptureStatEventJob;
use App\Models\Object_;
use App\Models\Room;
use App\Models\User;
use App\Services\Cabinet\AvailabilityToggleService;
use App\Services\Catalog\ObjectProfilePresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Public Object Profile
|--------------------------------------------------------------------------
|
| One type-aware composition: every section (rooms, prices, generic
| type-varying attributes, services) is present only because the object
| type declares it, never because of a branch on the type's own key, and
| every section degrades independently.
|
*/

/** @return array{languageId: int, countryId: int, cityTerritoryId: int} */
function publicObjectRegistry(): array
{
    $languageId = DB::table('languages')->insertGetId([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $countryId = DB::table('countries')->insertGetId([
        'code' => 'MD', 'currency' => 'MDL', 'phone_code' => '+373',
        'primary_language_id' => $languageId, 'is_active' => true, 'display_order' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $levelId = DB::table('territory_levels')->insertGetId([
        'country_id' => $countryId, 'depth_rank' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $countryRootId = DB::table('territories')->insertGetId([
        'country_id' => $countryId, 'level_id' => $levelId, 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('territory_translations')->insert([
        'territory_id' => $countryRootId, 'country_id' => $countryId, 'locale' => 'en', 'name' => 'Moldova Root', 'slug' => 'moldova-root',
        'full_slug_path' => 'moldova-root',
        'needs_review' => false, 'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);

    $regionId = DB::table('territories')->insertGetId([
        'country_id' => $countryId, 'level_id' => $levelId, 'parent_id' => $countryRootId, 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('territory_translations')->insert([
        'territory_id' => $regionId, 'country_id' => $countryId, 'locale' => 'en', 'name' => 'Central Region', 'slug' => 'central-region',
        'full_slug_path' => 'moldova-root/central-region',
        'needs_review' => false, 'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);

    $cityTerritoryId = DB::table('territories')->insertGetId([
        'country_id' => $countryId, 'level_id' => $levelId, 'parent_id' => $regionId, 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('territory_translations')->insert([
        'territory_id' => $cityTerritoryId, 'country_id' => $countryId, 'locale' => 'en', 'name' => 'Capital City', 'slug' => 'capital-city',
        'full_slug_path' => 'moldova-root/central-region/capital-city',
        'needs_review' => false, 'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);

    // Reviews ships default-enabled in production (ModuleSeeder); these
    // fixtures reproduce that baseline directly rather than assuming a
    // seeder ran, since module gating (a later task) reads this registry.
    DB::table('modules')->insert([
        'key' => 'reviews', 'default_state' => 'enabled',
        'scopable_levels' => json_encode(['portal', 'country', 'category', 'object']),
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return compact('languageId', 'countryId', 'cityTerritoryId');
}

/** @return array{typeId: int, categoryId: int} */
function publicObjectMakeAccommodationType(): array
{
    $categoryId = DB::table('object_types')->insertGetId([
        'key' => 'accommodation', 'is_active' => true, 'has_rooms' => false, 'has_availability_status' => false,
        'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('object_type_translations')->insert([
        'object_type_id' => $categoryId, 'locale' => 'en', 'name' => 'Accommodation', 'slug' => 'accommodation',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $typeId = DB::table('object_types')->insertGetId([
        'key' => 'hotel', 'parent_id' => $categoryId, 'is_active' => true,
        'has_rooms' => true, 'has_availability_status' => true,
        'attribute_schema' => json_encode([
            ['key' => 'house_rules', 'type' => 'text', 'label' => ['en' => 'House rules']],
        ]),
        'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('object_type_translations')->insert([
        'object_type_id' => $typeId, 'locale' => 'en', 'name' => 'Hotel', 'slug' => 'hotel',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return ['typeId' => $typeId, 'categoryId' => $categoryId];
}

function publicObjectMakeDiningType(): int
{
    $typeId = DB::table('object_types')->insertGetId([
        'key' => 'restaurant', 'is_active' => true, 'has_rooms' => false, 'has_availability_status' => false,
        'attribute_schema' => json_encode([
            ['key' => 'cuisine', 'type' => 'text', 'label' => ['en' => 'Cuisine']],
        ]),
        'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('object_type_translations')->insert([
        'object_type_id' => $typeId, 'locale' => 'en', 'name' => 'Restaurant', 'slug' => 'restaurant',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return $typeId;
}

/** @param  array<string, mixed>  $overrides */
function publicObjectMake(array $fixture, int $typeId, string $name, array $overrides = []): Object_
{
    $objectId = DB::table('objects')->insertGetId(array_merge([
        'ulid' => (string) Str::ulid(),
        'owner_id' => User::factory()->create()->id,
        'object_type_id' => $typeId,
        'territory_id' => $fixture['cityTerritoryId'],
        'country_id' => $fixture['countryId'],
        'status' => 'published', 'moderation_status' => 'approved',
        'availability_status' => 'available',
        'created_at' => now(), 'updated_at' => now(),
    ], $overrides));

    DB::table('object_translations')->insert([
        'object_id' => $objectId, 'locale' => 'en', 'name' => $name,
        'short_description' => 'A short teaser description.',
        'full_description' => 'The complete, long-form description of this object.',
        'slug' => Str::slug($name).'-'.$objectId, 'needs_review' => false, 'published_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    /** @var Object_ $object */
    $object = Object_::query()->findOrFail($objectId);

    return $object;
}

function publicObjectGiveAmenity(Object_ $object, string $groupLabel, string $amenityLabel): void
{
    $groupId = DB::table('amenity_groups')->insertGetId([
        'is_active' => true, 'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('amenity_group_translations')->insert([
        'amenity_group_id' => $groupId, 'locale' => 'en', 'name' => $groupLabel,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $amenityId = DB::table('amenities')->insertGetId([
        'amenity_group_id' => $groupId, 'is_filterable' => true, 'is_active' => true,
        'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('amenity_translations')->insert([
        'amenity_id' => $amenityId, 'locale' => 'en', 'name' => $amenityLabel,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('amenity_object')->insert(['amenity_id' => $amenityId, 'object_id' => $object->id]);
}

function publicObjectGiveRoom(Object_ $object, string $name): void
{
    $roomId = DB::table('rooms')->insertGetId([
        'object_id' => $object->id, 'capacity' => 2, 'room_count' => 3, 'area_sqm' => 24.5,
        'bed_configuration' => '1 double bed', 'max_guests' => 3, 'has_extra_bed' => true,
        'display_order' => 0, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('room_translations')->insert([
        'room_id' => $roomId, 'locale' => 'en', 'name' => $name, 'description' => 'A comfortable room.',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('prices')->insert([
        'priceable_type' => Room::class, 'priceable_id' => $roomId,
        'calculation_unit' => 'per_night', 'amount' => 75, 'currency' => 'EUR',
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function publicObjectGivePlacement(Object_ $object, string $badgeText = 'VIP'): void
{
    static $rank = 0;
    $rank++;

    $tierId = DB::table('placement_tiers')->insertGetId([
        'rank' => $rank, 'border_colour' => '#f8bb44', 'badge_colour' => '#f8bb44',
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('placement_tier_translations')->insert([
        'placement_tier_id' => $tierId, 'locale' => 'en', 'label' => $badgeText, 'badge_text' => $badgeText,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $packageId = DB::table('placement_packages')->insertGetId([
        'placement_tier_id' => $tierId, 'price' => 10, 'currency' => 'EUR', 'validity_days' => 30,
        'bump_allowed' => false, 'is_active' => true, 'display_order' => $rank,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('object_placements')->insert([
        'object_id' => $object->id, 'placement_package_id' => $packageId,
        'starts_at' => now()->subDays(1)->toDateString(), 'ends_at' => now()->addDays(30)->toDateString(),
        'internal_priority' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

it('composes breadcrumb, cover, gallery, header, descriptions, rooms, details, and services for an accommodation fixture — and omits the object-level prices block since rooms carry their own', function (): void {
    Storage::fake('public');
    $fixture = publicObjectRegistry();
    $type = publicObjectMakeAccommodationType();
    $object = publicObjectMake($fixture, $type['typeId'], 'Grand Test Hotel', [
        'attributes' => json_encode(['house_rules' => 'No smoking indoors.']),
    ]);

    $object->addMedia(UploadedFile::fake()->image('cover.jpg', 800, 600))->toMediaCollection('photos');
    $object->addMedia(UploadedFile::fake()->image('room.jpg', 800, 600))->toMediaCollection('photos');
    publicObjectGiveAmenity($object, 'General', 'Free Wi-Fi');
    publicObjectGiveRoom($object, 'Deluxe Double');
    publicObjectGivePlacement($object, 'VIP');

    DB::table('reviews')->insert([
        'object_id' => $object->id, 'rating' => 5, 'body' => 'Wonderful stay.', 'status' => 'published',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $response = $this->get(publicObjectUrl($object));

    $response->assertOk()
        ->assertSeeInOrder(['Moldova Root', 'Central Region', 'Capital City', 'Grand Test Hotel'])
        ->assertSee('Accommodation')
        ->assertSee('Hotel')
        ->assertSee('5.0 / 5')
        ->assertSee('A short teaser description.')
        ->assertSee('The complete, long-form description of this object.')
        ->assertSee('VIP')
        ->assertSee(__('public.catalog.card.availability.available'))
        ->assertSee(__('public.object.rooms_heading'))
        ->assertSee('Deluxe Double')
        ->assertSee('75')
        ->assertSee('EUR')
        ->assertSee(__('public.object.details_heading'))
        ->assertSee('House rules')
        ->assertSee('No smoking indoors.')
        ->assertSee(__('public.object.services_heading'))
        ->assertSee('Free Wi-Fi')
        ->assertDontSee(__('public.object.prices_heading'));

    $profile = app(ObjectProfilePresenter::class)->present($object->fresh());
    expect($profile->coverPhotoUrl)->not->toBeNull()
        ->and($profile->galleryPhotoUrls)->toHaveCount(1);
});

it('renders the object-level price block for a dining fixture with no rooms, and omits the rooms heading and availability badge entirely', function (): void {
    $fixture = publicObjectRegistry();
    $typeId = publicObjectMakeDiningType();
    $object = publicObjectMake($fixture, $typeId, 'Test Bistro', [
        'attributes' => json_encode(['cuisine' => 'Italian']),
    ]);

    DB::table('prices')->insert([
        'priceable_type' => Object_::class, 'priceable_id' => $object->id,
        'type' => 'Average cheque', 'calculation_unit' => 'per_person', 'amount' => 25, 'currency' => 'EUR',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $response = $this->get(publicObjectUrl($object));

    $response->assertOk()
        ->assertSee(__('public.object.prices_heading'))
        ->assertSee('Average cheque')
        ->assertSee('25')
        ->assertSee(__('public.object.details_heading'))
        ->assertSee('Cuisine')
        ->assertSee('Italian')
        ->assertDontSee(__('public.object.rooms_heading'))
        ->assertDontSee(__('public.catalog.card.availability.available'));
});

it('degrades independently — a fixture with a single photo and no optional data still renders a complete page, omitting every optional section', function (): void {
    Storage::fake('public');
    $fixture = publicObjectRegistry();
    $type = publicObjectMakeAccommodationType();
    $object = publicObjectMake($fixture, $type['typeId'], 'Bare Bones Hotel');
    $object->addMedia(UploadedFile::fake()->image('only.jpg', 800, 600))->toMediaCollection('photos');

    $response = $this->get(publicObjectUrl($object));

    $response->assertOk()
        ->assertSee('Bare Bones Hotel')
        ->assertDontSee(__('public.object.rooms_heading'))
        ->assertDontSee(__('public.object.prices_heading'))
        ->assertDontSee(__('public.object.details_heading'))
        ->assertDontSee(__('public.object.services_heading'));

    $profile = app(ObjectProfilePresenter::class)->present($object->fresh());
    expect($profile->galleryPhotoUrls)->toHaveCount(0);
});

it('never varies page content by placement package — only the tier badge differs between a VIP and an unranked fixture', function (): void {
    $fixture = publicObjectRegistry();
    $type = publicObjectMakeAccommodationType();
    $vip = publicObjectMake($fixture, $type['typeId'], 'VIP Hotel', [
        'attributes' => json_encode(['house_rules' => 'Quiet hours after 10pm.']),
    ]);
    $unranked = publicObjectMake($fixture, $type['typeId'], 'Unranked Hotel', [
        'attributes' => json_encode(['house_rules' => 'Quiet hours after 10pm.']),
    ]);
    publicObjectGiveRoom($vip, 'Standard Room');
    publicObjectGiveRoom($unranked, 'Standard Room');
    publicObjectGiveAmenity($vip, 'General', 'Free Parking');
    publicObjectGiveAmenity($unranked, 'General', 'Free Parking');
    publicObjectGivePlacement($vip, 'VIP');

    $vipResponse = $this->get(publicObjectUrl($vip));
    $unrankedResponse = $this->get(publicObjectUrl($unranked));

    $vipResponse->assertOk()->assertSee('VIP')
        ->assertSee(__('public.object.rooms_heading'))
        ->assertSee('Standard Room')
        ->assertSee('Quiet hours after 10pm.')
        ->assertSee(__('public.object.services_heading'))
        ->assertSee('Free Parking');

    $unrankedResponse->assertOk()
        ->assertSee(__('public.object.rooms_heading'))
        ->assertSee('Standard Room')
        ->assertSee('Quiet hours after 10pm.')
        ->assertSee(__('public.object.services_heading'))
        ->assertSee('Free Parking');

    // The unranked object's own page content carries no VIP badge — the
    // VIP fixture legitimately appears further down, in the unranked
    // object's own "similar objects" block (same territory and type),
    // showing its own badge on its own card, which this narrows past.
    $html = (string) $unrankedResponse->getContent();
    $servicesPosition = strpos($html, e(__('public.object.services_heading')));
    expect($servicesPosition)->not->toBeFalse();
    $ownContent = substr($html, 0, $servicesPosition);
    expect($ownContent)->not->toContain('VIP');
});

it('404s an object that is not published', function (): void {
    $fixture = publicObjectRegistry();
    $type = publicObjectMakeAccommodationType();
    $object = publicObjectMake($fixture, $type['typeId'], 'Draft Hotel', ['status' => 'draft']);

    $this->get(publicObjectUrl($object))->assertNotFound();
});

it('captures one object_page_view and at most one photo_view regardless of photo count, through EventCaptureService', function (): void {
    // F-19: this used to fire one photo_view per photo on every single page
    // view, whether or not the visitor ever opened the gallery — the stat
    // was structurally page views × photo count. Now it fires at most once
    // per render, present only when there is a gallery to show at all.
    Bus::fake();
    Storage::fake('public');
    $fixture = publicObjectRegistry();
    $type = publicObjectMakeAccommodationType();
    $object = publicObjectMake($fixture, $type['typeId'], 'Tracked Hotel');
    $object->addMedia(UploadedFile::fake()->image('a.jpg'))->toMediaCollection('photos');
    $object->addMedia(UploadedFile::fake()->image('b.jpg'))->toMediaCollection('photos');
    $object->addMedia(UploadedFile::fake()->image('c.jpg'))->toMediaCollection('photos');

    $this->get(publicObjectUrl($object))->assertOk();

    $dispatched = Bus::dispatched(CaptureStatEventJob::class)->map(fn (CaptureStatEventJob $job): array => [
        'kind' => (new ReflectionProperty($job, 'kind'))->getValue($job),
        'subjectId' => (new ReflectionProperty($job, 'subjectId'))->getValue($job),
    ]);

    expect($dispatched)->toHaveCount(2)
        ->and($dispatched->where('subjectId', $object->id)->where('kind', 'object_page_view'))->toHaveCount(1)
        ->and($dispatched->where('subjectId', $object->id)->where('kind', 'photo_view'))->toHaveCount(1);
});

it('emits no photo_view for a fixture with no gallery at all', function (): void {
    Bus::fake();
    $fixture = publicObjectRegistry();
    $type = publicObjectMakeAccommodationType();
    $object = publicObjectMake($fixture, $type['typeId'], 'Photoless Hotel');

    $this->get(publicObjectUrl($object))->assertOk();

    $dispatched = Bus::dispatched(CaptureStatEventJob::class)->map(fn (CaptureStatEventJob $job): string => (new ReflectionProperty($job, 'kind'))->getValue($job));

    expect($dispatched)->toContain('object_page_view')
        ->and($dispatched)->not->toContain('photo_view');
});

it('reflects an owner-toggled availability status immediately, not the profile cache left over from the previous request', function (): void {
    // F-08: the profile cache this page reads was written untagged, so
    // AvailabilityToggleService's own tagged flush never reached it — a
    // toggle was invisible on the object page until the TTL happened to
    // expire on its own, well past the toggle's whole point of immediacy.
    // The badge itself only ever renders the positive "available" state
    // (show.blade.php has no branch for unavailable/unspecified) — the
    // observable effect of a toggle away from "available" is the badge's
    // disappearance, not a second piece of text replacing it.
    $fixture = publicObjectRegistry();
    $type = publicObjectMakeAccommodationType();
    $owner = User::factory()->create();
    $object = publicObjectMake($fixture, $type['typeId'], 'Toggle Hotel', ['owner_id' => $owner->id]);

    $this->get(publicObjectUrl($object))
        ->assertOk()
        ->assertSee(__('public.catalog.card.availability.available'));

    app(AvailabilityToggleService::class)->toggle($object->fresh(), $owner);

    $this->get(publicObjectUrl($object))
        ->assertOk()
        ->assertDontSee(__('public.catalog.card.availability.available'));
});
