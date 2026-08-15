<?php

declare(strict_types=1);

use App\Jobs\CaptureStatEventJob;
use App\Models\Object_;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Public Contact Rail
|--------------------------------------------------------------------------
|
| The object page's own conversion element: every active channel, in
| order, each activation measured before the visitor is handed directly to
| the channel with no portal-side relay of the conversation itself.
|
*/

/** @return array{languageId: int, countryId: int, territoryId: int, typeId: int} */
function publicContactRailRegistry(): array
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
    $territoryId = DB::table('territories')->insertGetId([
        'country_id' => $countryId, 'level_id' => $levelId, 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('territory_translations')->insert([
        'territory_id' => $territoryId, 'locale' => 'en', 'name' => 'Capital City', 'slug' => 'capital-city',
        'needs_review' => false, 'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    $typeId = DB::table('object_types')->insertGetId([
        'key' => 'hotel', 'is_active' => true, 'has_rooms' => true, 'has_availability_status' => true,
        'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('object_type_translations')->insert([
        'object_type_id' => $typeId, 'locale' => 'en', 'name' => 'Hotel',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return compact('languageId', 'countryId', 'territoryId', 'typeId');
}

/** @param  array<string, mixed>  $overrides */
function publicContactRailMakeObject(array $fixture, string $name, array $overrides = []): Object_
{
    $objectId = DB::table('objects')->insertGetId(array_merge([
        'ulid' => (string) Str::ulid(),
        'owner_id' => User::factory()->create()->id,
        'object_type_id' => $fixture['typeId'],
        'territory_id' => $fixture['territoryId'],
        'country_id' => $fixture['countryId'],
        'status' => 'published', 'moderation_status' => 'approved',
        'availability_status' => 'available',
        'created_at' => now(), 'updated_at' => now(),
    ], $overrides));

    DB::table('object_translations')->insert([
        'object_id' => $objectId, 'locale' => 'en', 'name' => $name,
        'slug' => Str::slug($name).'-'.$objectId, 'needs_review' => false, 'published_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    /** @var Object_ $object */
    $object = Object_::query()->findOrFail($objectId);

    return $object;
}

/** @return array{typeId: int} */
function publicContactRailMakePhoneType(): array
{
    $typeId = DB::table('contact_channel_types')->insertGetId([
        'key' => 'phone', 'link_template' => 'tel:{value}', 'is_active' => true,
        'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('contact_channel_type_translations')->insert([
        'contact_channel_type_id' => $typeId, 'locale' => 'en', 'display_name' => 'Phone',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return compact('typeId');
}

function publicContactRailGiveChannel(Object_ $object, int $typeId, string $rawValue, int $displayOrder, bool $isActive = true): int
{
    return DB::table('contact_channels')->insertGetId([
        'object_id' => $object->id, 'contact_channel_type_id' => $typeId,
        'raw_value' => $rawValue, 'is_active' => $isActive, 'display_order' => $displayOrder,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

it("renders every active contact channel on the object page's own rail, in display order, each resolving through the deep-link service", function (): void {
    $fixture = publicContactRailRegistry();
    $phone = publicContactRailMakePhoneType();
    $whatsappTypeId = DB::table('contact_channel_types')->insertGetId([
        'key' => 'whatsapp', 'link_template' => 'https://wa.me/{value}', 'is_active' => true,
        'display_order' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('contact_channel_type_translations')->insert([
        'contact_channel_type_id' => $whatsappTypeId, 'locale' => 'en', 'display_name' => 'WhatsApp',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $object = publicContactRailMakeObject($fixture, 'Contactable Hotel');

    // Inserted out of order; the rail must still render by display_order.
    publicContactRailGiveChannel($object, $whatsappTypeId, '37360000001', displayOrder: 2);
    publicContactRailGiveChannel($object, $phone['typeId'], '37360000000', displayOrder: 1);
    publicContactRailGiveChannel($object, $phone['typeId'], '37360000002', displayOrder: 3, isActive: false);

    $response = $this->get(route('public.objects.show', ['lang' => 'en', 'object' => $object]));

    $response->assertOk()->assertSeeInOrder(['Phone', 'WhatsApp']);

    $html = (string) $response->getContent();
    expect(substr_count($html, 'objects/'.$object->id.'/contact/'))->toBe(2);
});

it('activates a channel by redirecting in one hop directly to its resolved deep link — no intermediate page, no portal relay of the conversation', function (): void {
    $fixture = publicContactRailRegistry();
    $phone = publicContactRailMakePhoneType();
    $object = publicContactRailMakeObject($fixture, 'Direct Hotel');
    $channelId = publicContactRailGiveChannel($object, $phone['typeId'], '37360000000', displayOrder: 0);

    $response = $this->get(route('public.objects.contact.click', [
        'lang' => 'en', 'object' => $object, 'channel' => $channelId,
    ]));

    $response->assertRedirect('tel:37360000000');
});

it('emits a contact_click event (object, channel, territory, language) before navigation — reachable even when the destination link itself fails to resolve', function (): void {
    $fixture = publicContactRailRegistry();
    $phone = publicContactRailMakePhoneType();
    $object = publicContactRailMakeObject($fixture, 'Measured Hotel');
    $channelId = publicContactRailGiveChannel($object, $phone['typeId'], '37360000000', displayOrder: 0);

    Bus::fake();

    $response = $this->get(route('public.objects.contact.click', [
        'lang' => 'en', 'object' => $object, 'channel' => $channelId,
    ]));

    $response->assertRedirect('tel:37360000000');
    Bus::assertDispatchedTimes(CaptureStatEventJob::class, 1);
    Bus::assertDispatched(CaptureStatEventJob::class, function (CaptureStatEventJob $job) use ($object, $phone): bool {
        return (new ReflectionProperty($job, 'kind'))->getValue($job) === 'contact_click'
            && (new ReflectionProperty($job, 'subjectId'))->getValue($job) === $object->id
            && (new ReflectionProperty($job, 'contactChannelTypeId'))->getValue($job) === $phone['typeId']
            && (new ReflectionProperty($job, 'territoryId'))->getValue($job) === $object->territory_id
            && (new ReflectionProperty($job, 'locale'))->getValue($job) === 'en';
    });

    // Falsification: even a channel whose link cannot be resolved (its
    // type carries no link_template — a real, data-driven misconfiguration,
    // not a mocked collaborator, since the resolver is final) must still
    // have its click measured — the visitor's intent to make contact is
    // real regardless of whether the technical hand-off can complete.
    $brokenTypeId = DB::table('contact_channel_types')->insertGetId([
        'key' => 'broken', 'link_template' => null, 'is_active' => true,
        'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('contact_channel_type_translations')->insert([
        'contact_channel_type_id' => $brokenTypeId, 'locale' => 'en', 'display_name' => 'Broken',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $brokenChannelId = publicContactRailGiveChannel($object, $brokenTypeId, 'anything', displayOrder: 1);

    Bus::fake();

    $unresolvableResponse = $this->get(route('public.objects.contact.click', [
        'lang' => 'en', 'object' => $object, 'channel' => $brokenChannelId,
    ]));

    $unresolvableResponse->assertNotFound();
    Bus::assertDispatchedTimes(CaptureStatEventJob::class, 1);
    Bus::assertDispatched(CaptureStatEventJob::class, function (CaptureStatEventJob $job) use ($object, $brokenTypeId): bool {
        return (new ReflectionProperty($job, 'kind'))->getValue($job) === 'contact_click'
            && (new ReflectionProperty($job, 'subjectId'))->getValue($job) === $object->id
            && (new ReflectionProperty($job, 'contactChannelTypeId'))->getValue($job) === $brokenTypeId;
    });
});

it('keeps contact channels functional regardless of availability status or placement tier, proven against fixtures at both extremes', function (): void {
    $fixture = publicContactRailRegistry();
    $phone = publicContactRailMakePhoneType();

    $unavailable = publicContactRailMakeObject($fixture, 'Unavailable Plain Hotel', ['availability_status' => 'unavailable']);
    publicContactRailGiveChannel($unavailable, $phone['typeId'], '37360000010', displayOrder: 0);

    $vip = publicContactRailMakeObject($fixture, 'Available VIP Hotel');
    publicContactRailGiveChannel($vip, $phone['typeId'], '37360000011', displayOrder: 0);
    $tierId = DB::table('placement_tiers')->insertGetId([
        'rank' => 1, 'border_colour' => '#f8bb44', 'badge_colour' => '#f8bb44',
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('placement_tier_translations')->insert([
        'placement_tier_id' => $tierId, 'locale' => 'en', 'label' => 'VIP', 'badge_text' => 'VIP',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $packageId = DB::table('placement_packages')->insertGetId([
        'placement_tier_id' => $tierId, 'price' => 10, 'currency' => 'EUR', 'validity_days' => 30,
        'bump_allowed' => false, 'is_active' => true, 'display_order' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('object_placements')->insert([
        'object_id' => $vip->id, 'placement_package_id' => $packageId,
        'starts_at' => now()->subDays(1)->toDateString(), 'ends_at' => now()->addDays(30)->toDateString(),
        'internal_priority' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $unavailableResponse = $this->get(route('public.objects.show', ['lang' => 'en', 'object' => $unavailable]));
    $vipResponse = $this->get(route('public.objects.show', ['lang' => 'en', 'object' => $vip]));

    $unavailableResponse->assertOk()->assertSee('Phone');
    $vipResponse->assertOk()->assertSee('Phone')->assertSee('VIP');
});

it('keeps the contact rail above the fold — it appears before the description and every section below it', function (): void {
    $fixture = publicContactRailRegistry();
    $phone = publicContactRailMakePhoneType();
    $object = publicContactRailMakeObject($fixture, 'Layout Hotel', [
        'attributes' => json_encode(['house_rules' => 'Quiet after 10pm.']),
    ]);
    DB::table('object_translations')->where('object_id', $object->id)->update([
        'short_description' => 'A short teaser for layout ordering.',
    ]);
    DB::table('object_types')->where('id', $fixture['typeId'])->update([
        'attribute_schema' => json_encode([
            ['key' => 'house_rules', 'type' => 'text', 'label' => ['en' => 'House rules']],
        ]),
    ]);
    publicContactRailGiveChannel($object, $phone['typeId'], '37360000000', displayOrder: 0);

    $html = (string) $this->get(route('public.objects.show', ['lang' => 'en', 'object' => $object]))->getContent();

    $railPosition = strpos($html, 'objects/'.$object->id.'/contact/');
    $descriptionPosition = strpos($html, 'A short teaser for layout ordering.');
    $detailsPosition = strpos($html, __('public.object.details_heading'));

    expect($railPosition)->not->toBeFalse()
        ->and($descriptionPosition)->not->toBeFalse()
        ->and($detailsPosition)->not->toBeFalse()
        ->and($railPosition)->toBeLessThan($descriptionPosition)
        ->and($railPosition)->toBeLessThan($detailsPosition);
});
