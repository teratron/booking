<?php

declare(strict_types=1);

use App\Jobs\CaptureStatEventJob;
use App\Models\Object_;
use App\Models\StatEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Event Emission Invariant — Never Blocks, Never Fails Visibly
|--------------------------------------------------------------------------
|
| Measurement fidelity has two guarantees that must hold on the real public
| request path, not merely against a bare EventCaptureService::capture()
| call: a capture-path failure must never surface to the visitor, and a
| capture write must never be synchronous on the request that triggers it.
| ObjectCardComponentTest, PublicObjectProfileTest, and PublicContactRailTest
| already prove each surface reaches EventCaptureService exactly once (or
| once per photo) per genuine interaction — this file does not repeat that,
| it proves the two resilience guarantees those tests do not cover.
|
*/

/** @return array{countryId: int, territoryId: int, typeId: int} */
function eventInvariantRegistry(): array
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
        'territory_id' => $territoryId, 'country_id' => $countryId, 'locale' => 'en', 'name' => 'Emission City', 'slug' => 'emission-city',
        'full_slug_path' => 'emission-city',
        'needs_review' => false, 'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    $typeId = DB::table('object_types')->insertGetId([
        'key' => 'hotel-emission', 'is_active' => true, 'has_rooms' => false, 'has_availability_status' => true,
        'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('object_type_translations')->insert([
        'object_type_id' => $typeId, 'locale' => 'en', 'name' => 'Hotel', 'slug' => 'hotel',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return compact('countryId', 'territoryId', 'typeId');
}

/** @param  array<string, mixed>  $overrides */
function eventInvariantMakeObject(array $fixture, string $name, array $overrides = []): Object_
{
    $objectId = DB::table('objects')->insertGetId(array_merge([
        'ulid' => (string) Str::ulid(),
        'owner_id' => User::factory()->create()->id,
        'object_type_id' => $fixture['typeId'],
        'territory_id' => $fixture['territoryId'],
        'country_id' => $fixture['countryId'],
        'status' => 'published', 'moderation_status' => 'approved', 'availability_status' => 'available',
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

function eventInvariantGivePhoneChannel(Object_ $object): int
{
    $typeId = DB::table('contact_channel_types')->insertGetId([
        'key' => 'phone', 'link_template' => 'tel:{value}', 'is_active' => true,
        'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('contact_channel_type_translations')->insert([
        'contact_channel_type_id' => $typeId, 'locale' => 'en', 'display_name' => 'Phone',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return DB::table('contact_channels')->insertGetId([
        'object_id' => $object->id, 'contact_channel_type_id' => $typeId,
        'raw_value' => '37360000000', 'is_active' => true, 'display_order' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

it('keeps a contact-click redirect completing even when the capture path is forced to throw', function (): void {
    // The same technique EventCaptureServiceTest already established for a
    // bare capture() call — a queue connection that does not exist forces
    // the underlying dispatch to throw — exercised here against the real
    // controller/HTTP path instead, since a try/catch proven around a unit
    // call says nothing about whether every caller on the request path
    // still reaches its own return value.
    config()->set('queue.default', 'nonexistent-connection');

    $fixture = eventInvariantRegistry();
    $object = eventInvariantMakeObject($fixture, 'Resilient Hotel');
    $channelId = eventInvariantGivePhoneChannel($object);

    $response = $this->get(route('public.objects.contact.click', [
        'lang' => 'en', 'object' => $object, 'channel' => $channelId,
    ]));

    $response->assertRedirect('tel:37360000000');
    expect(StatEvent::query()->count())->toBe(0);
});

it('keeps the object page itself rendering even when the capture path is forced to throw', function (): void {
    config()->set('queue.default', 'nonexistent-connection');

    $fixture = eventInvariantRegistry();
    $object = eventInvariantMakeObject($fixture, 'Resilient Profile Hotel');

    $response = $this->get(publicObjectUrl($object));

    $response->assertOk()->assertSee('Resilient Profile Hotel');
    expect(StatEvent::query()->count())->toBe(0);
});

it('never writes a stat event synchronously when a card renders', function (): void {
    Queue::fake();
    $fixture = eventInvariantRegistry();
    $object = eventInvariantMakeObject($fixture, 'Queued Card Hotel');

    $this->blade('<x-object-card :object="$object" />', ['object' => $object->fresh()]);

    expect(StatEvent::query()->count())->toBe(0);
    Queue::assertPushed(CaptureStatEventJob::class, 1);
});

it('never writes stat events synchronously when the object page renders, page-view and every photo-view included', function (): void {
    Queue::fake();
    Storage::fake('public');
    $fixture = eventInvariantRegistry();
    $object = eventInvariantMakeObject($fixture, 'Queued Profile Hotel');
    $object->addMedia(UploadedFile::fake()->image('a.jpg'))->toMediaCollection('photos');
    $object->addMedia(UploadedFile::fake()->image('b.jpg'))->toMediaCollection('photos');

    $this->get(publicObjectUrl($object))->assertOk();

    expect(StatEvent::query()->count())->toBe(0);
    Queue::assertPushed(CaptureStatEventJob::class, 3);
});

it('never writes a stat event synchronously when a contact click redirects', function (): void {
    Queue::fake();
    $fixture = eventInvariantRegistry();
    $object = eventInvariantMakeObject($fixture, 'Queued Click Hotel');
    $channelId = eventInvariantGivePhoneChannel($object);

    $response = $this->get(route('public.objects.contact.click', [
        'lang' => 'en', 'object' => $object, 'channel' => $channelId,
    ]));

    $response->assertRedirect('tel:37360000000');
    expect(StatEvent::query()->count())->toBe(0);
    Queue::assertPushed(CaptureStatEventJob::class, 1);
});
