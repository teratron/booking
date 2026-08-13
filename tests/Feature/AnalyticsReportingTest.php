<?php

declare(strict_types=1);

use App\Filament\Admin\Pages\AnalyticsReport;
use App\Models\Banner;
use App\Models\Object_;
use App\Models\User;
use App\Services\Analytics\AnalyticsReportingService;
use App\Services\Analytics\EventCaptureService;
use App\Services\Analytics\TrafficSourceRecorder;
use App\Support\Analytics\TrafficSourceChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Analytics Reporting & Traffic Sources
|--------------------------------------------------------------------------
|
| Five contracts: the report rolls up correctly across every named
| dimension, it is refused without analytics.view, a traffic-source record
| never stores more than channel + host + campaign, first-touch capture
| never fires twice in one visit, and the owner-scoped query never leaks a
| figure belonging to another owner's objects.
|
*/

function reportingActor(array $permissions, ?string $roleKey = null): User
{
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $role = Role::findOrCreate($roleKey ?? 'reporting_role', 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $role->syncPermissions($permissions);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user->fresh();
}

/** @return array{country: int, territoryA: int, territoryB: int, typeHotel: int, typeRestaurant: int} */
function reportingFixtureGeography(): array
{
    $languageId = DB::table('languages')->insertGetId([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('languages')->insert([
        'code' => 'ru', 'short_label' => 'RU', 'is_active' => true, 'is_primary' => false,
        'display_order' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $countryId = DB::table('countries')->insertGetId([
        'code' => 'MD', 'currency' => 'MDL', 'phone_code' => '+373',
        'primary_language_id' => $languageId, 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $levelId = DB::table('territory_levels')->insertGetId([
        'country_id' => $countryId, 'depth_rank' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $territoryA = DB::table('territories')->insertGetId([
        'country_id' => $countryId, 'level_id' => $levelId, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $territoryB = DB::table('territories')->insertGetId([
        'country_id' => $countryId, 'level_id' => $levelId, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $typeHotel = DB::table('object_types')->insertGetId([
        'key' => 'hotel', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $typeRestaurant = DB::table('object_types')->insertGetId([
        'key' => 'restaurant', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return compact('countryId', 'territoryA', 'territoryB', 'typeHotel', 'typeRestaurant');
}

function reportingFixtureObject(int $ownerId, int $countryId, int $territoryId, int $typeId): int
{
    return DB::table('objects')->insertGetId([
        'ulid' => (string) Str::ulid(), 'owner_id' => $ownerId,
        'object_type_id' => $typeId, 'territory_id' => $territoryId, 'country_id' => $countryId,
        'status' => 'published', 'moderation_status' => 'approved',
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function reportingFixtureBanner(): int
{
    $slotId = DB::table('banner_slots')->insertGetId([
        'key' => 'reporting-test-slot', 'surfaces' => json_encode(['home']),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return DB::table('banners')->insertGetId([
        'banner_slot_id' => $slotId, 'name' => 'Reporting Test Banner',
        'advertiser' => 'Test advertiser', 'destination_link' => 'https://example.test',
        'starts_at' => now()->toDateString(), 'ends_at' => now()->addMonth()->toDateString(),
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function insertDaily(array $attributes): void
{
    DB::table('stat_dailies')->insert(array_merge([
        'created_at' => now(), 'updated_at' => now(),
    ], $attributes));
}

it('rolls up correctly across every named dimension: period, object, city, country, category, language, banner', function (): void {
    $geo = reportingFixtureGeography();
    $owner = User::factory()->create();
    $objectA = reportingFixtureObject($owner->id, $geo['countryId'], $geo['territoryA'], $geo['typeHotel']);
    $objectB = reportingFixtureObject($owner->id, $geo['countryId'], $geo['territoryB'], $geo['typeRestaurant']);
    $bannerId = reportingFixtureBanner();

    // A third territory and category, used only for the out-of-period row
    // below, so it cannot accidentally satisfy any of the other
    // single-dimension assertions (object, city, category, language).
    $territoryC = DB::table('territories')->insertGetId([
        'country_id' => $geo['countryId'], 'level_id' => DB::table('territory_levels')->where('country_id', $geo['countryId'])->value('id'),
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $typeApartment = DB::table('object_types')->insertGetId([
        'key' => 'apartment', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $objectC = reportingFixtureObject($owner->id, $geo['countryId'], $territoryC, $typeApartment);

    insertDaily([
        'date' => '2026-08-01', 'subject_type' => Object_::class, 'subject_id' => $objectA,
        'kind' => 'object_page_view', 'territory_id' => $geo['territoryA'], 'country_id' => $geo['countryId'],
        'locale' => 'en', 'count' => 5,
    ]);
    insertDaily([
        'date' => '2026-08-01', 'subject_type' => Object_::class, 'subject_id' => $objectB,
        'kind' => 'object_page_view', 'territory_id' => $geo['territoryB'], 'country_id' => $geo['countryId'],
        'locale' => 'ru', 'count' => 3,
    ]);
    // Outside the period window used below, and off every other dimension
    // the assertions below check individually.
    insertDaily([
        'date' => '2026-09-15', 'subject_type' => Object_::class, 'subject_id' => $objectC,
        'kind' => 'object_page_view', 'territory_id' => $territoryC, 'country_id' => $geo['countryId'],
        'locale' => 'en', 'count' => 99,
    ]);
    insertDaily([
        'date' => '2026-08-01', 'subject_type' => Banner::class, 'subject_id' => $bannerId,
        'kind' => 'banner_impression', 'country_id' => $geo['countryId'], 'count' => 10,
    ]);

    $service = app(AnalyticsReportingService::class);

    expect($service->summary(['object_id' => $objectA])['object_page_view'])->toBe(5)
        ->and($service->summary(['object_id' => $objectB])['object_page_view'])->toBe(3)
        ->and($service->summary(['territory_id' => $geo['territoryB']])['object_page_view'])->toBe(3)
        ->and($service->summary(['country_id' => $geo['countryId'], 'period_from' => '2026-08-01', 'period_until' => '2026-08-31'])['object_page_view'])->toBe(8)
        ->and($service->summary(['object_type_id' => $geo['typeHotel']])['object_page_view'])->toBe(5)
        ->and($service->summary(['object_type_id' => $geo['typeRestaurant']])['object_page_view'])->toBe(3)
        ->and($service->summary(['locale' => 'ru'])['object_page_view'])->toBe(3)
        ->and($service->summary(['banner_id' => $bannerId])['banner_impression'])->toBe(10)
        ->and($service->summary(['period_from' => '2026-09-01'])['object_page_view'])->toBe(99);
});

it('refuses the analytics report to an actor without analytics.view and admits one who has it', function (): void {
    $permitted = reportingActor(['admin_panel_access', 'analytics.view'], 'analytics_admin');
    $refused = reportingActor(['admin_panel_access'], 'no_analytics_admin');

    $this->actingAs($permitted)->get(AnalyticsReport::getUrl(panel: 'admin'))->assertSuccessful();
    $this->actingAs($refused)->get(AnalyticsReport::getUrl(panel: 'admin'))->assertForbidden();
});

it('resolves a traffic source down to channel, host, and campaign only — never a full referrer URL', function (): void {
    $recorder = app(TrafficSourceRecorder::class);

    $source = $recorder->firstTouch('https://www.google.com/search?q=hotels+in+chisinau#results', null);

    expect($source)->not->toBeNull()
        ->and($source->channel)->toBe(TrafficSourceChannel::Search)
        ->and($source->domain)->toBe('www.google.com')
        ->and($source->campaign)->toBeNull();

    // The coarse shape, proven directly: no scheme, no path, no query
    // string, no fragment survives into the stored domain.
    expect($source->domain)->not->toContain('/')
        ->and($source->domain)->not->toContain('?')
        ->and($source->domain)->not->toContain('#')
        ->and($source->domain)->not->toContain('https');
});

it('classifies a campaign tag as campaign regardless of referrer', function (): void {
    $withCampaign = app(TrafficSourceRecorder::class)->firstTouch('https://www.instagram.com/p/xyz', 'summer-sale');

    expect($withCampaign)->not->toBeNull()
        ->and($withCampaign->channel)->toBe(TrafficSourceChannel::Campaign)
        ->and($withCampaign->campaign)->toBe('summer-sale');
});

it('classifies a bare visit with neither a referrer nor a campaign tag as direct', function (): void {
    $direct = app(TrafficSourceRecorder::class)->firstTouch(null, null);

    expect($direct)->not->toBeNull()
        ->and($direct->channel)->toBe(TrafficSourceChannel::Direct)
        ->and($direct->domain)->toBeNull();
});

it('captures the traffic source on the first event of a visit only — a second event neither overwrites nor duplicates it', function (): void {
    $geo = reportingFixtureGeography();
    $owner = User::factory()->create();
    $objectId = reportingFixtureObject($owner->id, $geo['countryId'], $geo['territoryA'], $geo['typeHotel']);
    $object = Object_::query()->findOrFail($objectId);

    $capture = app(EventCaptureService::class);

    $capture->capture('object_page_view', $object, [
        'source' => ['referrer_url' => 'https://www.google.com/search?q=hotels', 'campaign' => null],
    ]);
    $capture->capture('photo_view', $object, [
        'source' => ['referrer_url' => 'https://www.bing.com/search?q=other', 'campaign' => null],
    ]);

    $events = DB::table('stat_events')->orderBy('id')->get();
    expect($events)->toHaveCount(2);

    expect($events[0]->kind)->toBe('object_page_view')
        ->and($events[0]->source_channel)->toBe('search')
        ->and($events[0]->source_domain)->toBe('www.google.com');

    // The second event of the same visit carries no source data at all —
    // not the second referrer, not a repeat of the first.
    expect($events[1]->kind)->toBe('photo_view')
        ->and($events[1]->source_channel)->toBeNull()
        ->and($events[1]->source_domain)->toBeNull();
});

it('returns figures for exactly one owner\'s own objects, proven against a fixture with two owners', function (): void {
    $geo = reportingFixtureGeography();
    $ownerOne = User::factory()->create();
    $ownerTwo = User::factory()->create();

    $ownerOneObject = reportingFixtureObject($ownerOne->id, $geo['countryId'], $geo['territoryA'], $geo['typeHotel']);
    $ownerTwoObject = reportingFixtureObject($ownerTwo->id, $geo['countryId'], $geo['territoryB'], $geo['typeRestaurant']);

    insertDaily([
        'date' => '2026-08-01', 'subject_type' => Object_::class, 'subject_id' => $ownerOneObject,
        'kind' => 'object_page_view', 'locale' => 'en', 'count' => 7,
    ]);
    insertDaily([
        'date' => '2026-08-01', 'subject_type' => Object_::class, 'subject_id' => $ownerTwoObject,
        'kind' => 'object_page_view', 'locale' => 'en', 'count' => 4,
    ]);

    $service = app(AnalyticsReportingService::class);

    expect($service->forOwner($ownerOne->id)['object_page_view'])->toBe(7)
        ->and($service->forOwner($ownerTwo->id)['object_page_view'])->toBe(4);
});
