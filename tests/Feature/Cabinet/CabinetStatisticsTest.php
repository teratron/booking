<?php

declare(strict_types=1);

use App\Filament\Cabinet\Pages\Statistics;
use App\Models\Object_;
use App\Models\User;
use App\Services\Cabinet\ObjectStatisticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Cabinet Statistics
|--------------------------------------------------------------------------
|
| The owner cabinet's dedicated, all-time-only statistics page for whichever
| object is currently selected as the Filament tenant: page views, photo
| views, a per-channel contact-click breakdown, a traffic-source breakdown,
| and the favorite count. Every figure here reads real seeded rows — nothing
| is a widget stub — and an owner must never see another owner's numbers,
| asserted both by direct value comparison and by the routing layer refusing
| a cross-owner tenant outright.
|
*/

/** @return array{languageId: int, countryId: int, territoryId: int, typeId: int} */
function cabinetStatisticsGeography(): array
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
    $typeId = DB::table('object_types')->insertGetId([
        'key' => 'accommodation', 'is_active' => true, 'has_rooms' => false,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return compact('languageId', 'countryId', 'territoryId', 'typeId');
}

function cabinetStatisticsMakeObject(array $fixture, int $ownerId, string $name): Object_
{
    $objectId = DB::table('objects')->insertGetId([
        'ulid' => (string) Str::ulid(),
        'owner_id' => $ownerId,
        'object_type_id' => $fixture['typeId'],
        'territory_id' => $fixture['territoryId'],
        'country_id' => $fixture['countryId'],
        'status' => 'published',
        'moderation_status' => 'approved',
        'availability_status' => 'available',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    DB::table('object_translations')->insert([
        'object_id' => $objectId, 'locale' => 'en', 'name' => $name,
        'slug' => Str::slug($name), 'needs_review' => false, 'published_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    /** @var Object_ $object */
    $object = Object_::query()->withUnmoderated()->findOrFail($objectId);

    return $object;
}

function cabinetStatisticsOwner(string $roleKey): User
{
    foreach (['object.view', 'object.edit', 'cabinet_access'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $role = Role::findOrCreate($roleKey, 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $role->syncPermissions(['object.view', 'object.edit', 'cabinet_access']);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user->fresh();
}

/**
 * Seeds three contact channel types — two with an English display name, one
 * deliberately left untranslated to exercise the label-fallback path.
 *
 * @return array{whatsapp: int, viber: int, sms: int}
 */
function cabinetStatisticsSeedContactChannelTypes(): array
{
    $ids = [];

    foreach (['whatsapp' => 'WhatsApp', 'viber' => 'Viber', 'sms' => null] as $key => $displayName) {
        $ids[$key] = DB::table('contact_channel_types')->insertGetId([
            'key' => $key, 'is_active' => true, 'display_order' => count($ids) + 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        if ($displayName !== null) {
            DB::table('contact_channel_type_translations')->insert([
                'contact_channel_type_id' => $ids[$key], 'locale' => 'en', 'display_name' => $displayName,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    return $ids;
}

function cabinetStatisticsSeedPageViews(Object_ $object): void
{
    foreach ([5, 3] as $count) {
        DB::table('stat_dailies')->insert([
            'date' => now()->toDateString(), 'subject_type' => Object_::class, 'subject_id' => $object->id,
            'kind' => 'object_page_view', 'count' => $count,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}

function cabinetStatisticsSeedPhotoViews(Object_ $object): void
{
    foreach ([3, 1] as $count) {
        DB::table('stat_dailies')->insert([
            'date' => now()->toDateString(), 'subject_type' => Object_::class, 'subject_id' => $object->id,
            'kind' => 'photo_view', 'count' => $count,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}

/** @param  array{whatsapp: int, viber: int, sms: int}  $channelIds */
function cabinetStatisticsSeedContactClicks(Object_ $object, array $channelIds): void
{
    $rows = [
        ['channel' => $channelIds['whatsapp'], 'count' => 2],
        ['channel' => $channelIds['viber'], 'count' => 5],
        ['channel' => $channelIds['sms'], 'count' => 1],
    ];

    foreach ($rows as $row) {
        DB::table('stat_dailies')->insert([
            'date' => now()->toDateString(), 'subject_type' => Object_::class, 'subject_id' => $object->id,
            'kind' => 'contact_click', 'contact_channel_type_id' => $row['channel'], 'count' => $row['count'],
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}

function cabinetStatisticsSeedTrafficSources(Object_ $object): void
{
    $rows = [
        ['channel' => 'search', 'domain' => 'google.com', 'campaign' => null, 'times' => 2],
        ['channel' => 'social', 'domain' => 'facebook.com', 'campaign' => null, 'times' => 1],
        ['channel' => 'campaign', 'domain' => null, 'campaign' => 'summer-sale', 'times' => 3],
    ];

    foreach ($rows as $row) {
        for ($i = 0; $i < $row['times']; $i++) {
            DB::table('stat_events')->insert([
                'kind' => 'object_page_view',
                'subject_type' => Object_::class,
                'subject_id' => $object->id,
                'occurred_at' => now(),
                'source_channel' => $row['channel'],
                'source_domain' => $row['domain'],
                'source_campaign' => $row['campaign'],
            ]);
        }
    }
}

function cabinetStatisticsSeedFavorites(Object_ $object, int $count, ?Object_ $decoyObject = null, int $decoyCount = 0): void
{
    for ($i = 0; $i < $count; $i++) {
        DB::table('favorites')->insert([
            'object_id' => $object->id, 'browser_token' => (string) Str::uuid(), 'created_at' => now(),
        ]);
    }

    // A different, genuinely existing object's favorites must never leak
    // into this one's count.
    if ($decoyObject instanceof Object_ && $decoyCount > 0) {
        for ($i = 0; $i < $decoyCount; $i++) {
            DB::table('favorites')->insert([
                'object_id' => $decoyObject->id, 'browser_token' => (string) Str::uuid(), 'created_at' => now(),
            ]);
        }
    }
}

it('reads real page views, photo views, contact-click, traffic-source, and favorite figures for the tenant object', function (): void {
    $fixture = cabinetStatisticsGeography();
    $owner = cabinetStatisticsOwner('statistics_owner_render');
    $object = cabinetStatisticsMakeObject($fixture, $owner->id, 'Seaside Villa');
    $decoyObject = cabinetStatisticsMakeObject($fixture, $owner->id, 'Decoy Chalet');
    $channelIds = cabinetStatisticsSeedContactChannelTypes();

    cabinetStatisticsSeedPageViews($object);
    cabinetStatisticsSeedPhotoViews($object);
    cabinetStatisticsSeedContactClicks($object, $channelIds);
    cabinetStatisticsSeedTrafficSources($object);
    cabinetStatisticsSeedFavorites($object, count: 2, decoyObject: $decoyObject, decoyCount: 1);

    $summary = app(ObjectStatisticsService::class)->summarize($object);

    expect($summary->objectName)->toBe('Seaside Villa')
        ->and($summary->pageViews)->toBe(8)
        ->and($summary->photoViews)->toBe(4)
        ->and($summary->contactClicksTotal)->toBe(8)
        ->and($summary->favoriteCount)->toBe(2);

    $channels = collect($summary->channelClicks)->keyBy('channelKey');
    expect($channels)->toHaveCount(3)
        ->and($channels['whatsapp']->label)->toBe('WhatsApp')
        ->and($channels['whatsapp']->count)->toBe(2)
        ->and($channels['viber']->label)->toBe('Viber')
        ->and($channels['viber']->count)->toBe(5)
        // Untranslated channel: the label falls back to a humanized key
        // rather than a blank or a raw machine key.
        ->and($channels['sms']->label)->toBe('Sms')
        ->and($channels['sms']->count)->toBe(1)
        // Sorted by count, descending — the highest-volume channel leads.
        ->and($summary->channelClicks[0]->channelKey)->toBe('viber');

    expect($summary->trafficSources)->toHaveCount(3);
    $bySourceChannel = collect($summary->trafficSources)->keyBy(fn ($row) => $row->channel->value);
    expect($bySourceChannel['search']->domain)->toBe('google.com')
        ->and($bySourceChannel['search']->campaign)->toBeNull()
        ->and($bySourceChannel['search']->count)->toBe(2)
        ->and($bySourceChannel['social']->domain)->toBe('facebook.com')
        ->and($bySourceChannel['social']->count)->toBe(1)
        ->and($bySourceChannel['campaign']->domain)->toBeNull()
        ->and($bySourceChannel['campaign']->campaign)->toBe('summer-sale')
        ->and($bySourceChannel['campaign']->count)->toBe(3)
        // Sorted by count, descending.
        ->and($summary->trafficSources[0]->channel->value)->toBe('campaign');
});

it('reads zero counts and empty breakdowns honestly for an object with no recorded activity yet', function (): void {
    $fixture = cabinetStatisticsGeography();
    $owner = cabinetStatisticsOwner('statistics_owner_no_activity');
    $object = cabinetStatisticsMakeObject($fixture, $owner->id, 'Untouched Chalet');

    $summary = app(ObjectStatisticsService::class)->summarize($object);

    expect($summary->pageViews)->toBe(0)
        ->and($summary->photoViews)->toBe(0)
        ->and($summary->contactClicksTotal)->toBe(0)
        ->and($summary->favoriteCount)->toBe(0)
        ->and($summary->channelClicks)->toBe([])
        ->and($summary->trafficSources)->toBe([]);
});

it('renders the page end to end for an authenticated owner, showing the object name and breakdown labels', function (): void {
    $fixture = cabinetStatisticsGeography();
    $owner = cabinetStatisticsOwner('statistics_owner_http_render');
    $object = cabinetStatisticsMakeObject($fixture, $owner->id, 'Mountain Lodge');
    $channelIds = cabinetStatisticsSeedContactChannelTypes();

    cabinetStatisticsSeedContactClicks($object, $channelIds);
    cabinetStatisticsSeedTrafficSources($object);

    $response = $this->actingAs($owner)->get(Statistics::getUrl(panel: 'cabinet', tenant: $object));

    $response->assertSuccessful()
        ->assertSee('Mountain Lodge')
        ->assertSee('WhatsApp')
        ->assertSee('Viber')
        ->assertSee(__('panel.cabinet.statistics.traffic_channels.search'))
        ->assertSee(__('panel.cabinet.statistics.traffic_channels.campaign'));
});

it("shows an owner only their own object's figures, never another owner's", function (): void {
    $fixture = cabinetStatisticsGeography();
    $ownerA = cabinetStatisticsOwner('statistics_owner_isolation_a');
    $ownerB = cabinetStatisticsOwner('statistics_owner_isolation_b');
    $objectA = cabinetStatisticsMakeObject($fixture, $ownerA->id, 'Owner A Villa');
    $objectB = cabinetStatisticsMakeObject($fixture, $ownerB->id, 'Owner B Villa');

    DB::table('stat_dailies')->insert([
        'date' => now()->toDateString(), 'subject_type' => Object_::class, 'subject_id' => $objectA->id,
        'kind' => 'object_page_view', 'count' => 11, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('stat_dailies')->insert([
        'date' => now()->toDateString(), 'subject_type' => Object_::class, 'subject_id' => $objectB->id,
        'kind' => 'object_page_view', 'count' => 57, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $summaryA = app(ObjectStatisticsService::class)->summarize($objectA);
    $summaryB = app(ObjectStatisticsService::class)->summarize($objectB);

    expect($summaryA->pageViews)->toBe(11)
        ->and($summaryB->pageViews)->toBe(57)
        ->and($summaryA->pageViews)->not->toBe($summaryB->pageViews);

    // The routing layer itself must refuse ownerA reaching objectB's tenant
    // page outright — the figures above are never even a query away from
    // leaking, because the request never reaches this page's own code.
    $this->actingAs($ownerA)
        ->get(Statistics::getUrl(panel: 'cabinet', tenant: $objectA))
        ->assertSuccessful();

    $this->actingAs($ownerA)
        ->get(Statistics::getUrl(panel: 'cabinet', tenant: $objectB))
        ->assertNotFound();
});
