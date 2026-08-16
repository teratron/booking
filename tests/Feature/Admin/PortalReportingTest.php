<?php

declare(strict_types=1);

use App\Models\Banner;
use App\Models\Object_;
use App\Models\ObjectType;
use App\Models\User;
use App\Services\Analytics\PortalReportingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Portal-Wide Reporting — Derived Figures
|--------------------------------------------------------------------------
|
| The eight figures `[TZ]` §89/§125 name beyond AnalyticsReportingService's
| own per-kind totals. Most viewed objects, most popular categories, and
| banner click-through rate read the aggregate stat_dailies tier; the other
| five read their own operational tables directly and never touch
| stat_events at all — proven below by query-log inspection, not by
| reasoning about the code.
|
*/

function portalReportingFixture(): array
{
    $languageId = DB::table('languages')->insertGetId([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'display_order' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $countryId = DB::table('countries')->insertGetId([
        'code' => 'MD', 'currency' => 'MDL', 'phone_code' => '+373', 'primary_language_id' => $languageId,
        'is_active' => true, 'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $levelId = DB::table('territory_levels')->insertGetId([
        'country_id' => $countryId, 'depth_rank' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $territoryId = DB::table('territories')->insertGetId([
        'country_id' => $countryId, 'level_id' => $levelId, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $typeId = DB::table('object_types')->insertGetId([
        'key' => 'accommodation', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('object_type_translations')->insert([
        'object_type_id' => $typeId, 'locale' => 'en', 'name' => 'Accommodation',
        'slug' => 'accommodation', 'created_at' => now(), 'updated_at' => now(),
    ]);

    Role::findOrCreate('object_owner', 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $ownerInPeriod = User::factory()->create(['created_at' => now()->subDays(5)]);
    $ownerInPeriod->assignRole('object_owner');

    $ownerOutsidePeriod = User::factory()->create(['created_at' => now()->subDays(60)]);
    $ownerOutsidePeriod->assignRole('object_owner');

    $objectInPeriod = DB::table('objects')->insertGetId([
        'ulid' => (string) Str::ulid(), 'owner_id' => $ownerInPeriod->id,
        'object_type_id' => $typeId, 'territory_id' => $territoryId, 'country_id' => $countryId,
        'status' => 'published', 'created_at' => now()->subDays(3), 'updated_at' => now(),
    ]);
    DB::table('object_translations')->insert([
        'object_id' => $objectInPeriod, 'locale' => 'en', 'name' => 'Most Viewed Object',
        'slug' => 'most-viewed-object', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $objectOutsidePeriod = DB::table('objects')->insertGetId([
        'ulid' => (string) Str::ulid(), 'owner_id' => $ownerOutsidePeriod->id,
        'object_type_id' => $typeId, 'territory_id' => $territoryId, 'country_id' => $countryId,
        'status' => 'published', 'created_at' => now()->subDays(60), 'updated_at' => now(),
    ]);
    DB::table('object_translations')->insert([
        'object_id' => $objectOutsidePeriod, 'locale' => 'en', 'name' => 'Old Object',
        'slug' => 'old-object', 'created_at' => now(), 'updated_at' => now(),
    ]);

    return compact('countryId', 'territoryId', 'typeId', 'objectInPeriod', 'objectOutsidePeriod');
}

it('counts most viewed objects, most popular categories, and banner CTR from the aggregate tier', function (): void {
    $geo = portalReportingFixture();

    DB::table('stat_dailies')->insert([
        [
            'date' => now()->subDays(2)->toDateString(), 'subject_type' => Object_::class,
            'subject_id' => $geo['objectInPeriod'], 'kind' => 'object_page_view',
            'territory_id' => $geo['territoryId'], 'country_id' => $geo['countryId'], 'locale' => 'en',
            'count' => 7, 'created_at' => now(), 'updated_at' => now(),
        ],
        [
            'date' => now()->subDays(2)->toDateString(), 'subject_type' => Object_::class,
            'subject_id' => $geo['objectOutsidePeriod'], 'kind' => 'object_page_view',
            'territory_id' => $geo['territoryId'], 'country_id' => $geo['countryId'], 'locale' => 'en',
            'count' => 3, 'created_at' => now(), 'updated_at' => now(),
        ],
    ]);

    $bannerSlotId = DB::table('banner_slots')->insertGetId([
        'key' => 'reporting_probe_slot', 'surfaces' => json_encode(['home']),
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $bannerId = DB::table('banners')->insertGetId([
        'banner_slot_id' => $bannerSlotId, 'name' => 'Probe Banner', 'advertiser' => 'Acme',
        'destination_link' => 'https://example.test', 'display_order' => 0,
        'starts_at' => now()->toDateString(), 'ends_at' => now()->addDays(30)->toDateString(),
        'is_active' => true, 'impressions' => 0, 'clicks' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('stat_dailies')->insert([
        [
            'date' => now()->subDays(1)->toDateString(), 'subject_type' => Banner::class,
            'subject_id' => $bannerId, 'kind' => 'banner_impression',
            'count' => 100, 'created_at' => now(), 'updated_at' => now(),
        ],
        [
            'date' => now()->subDays(1)->toDateString(), 'subject_type' => Banner::class,
            'subject_id' => $bannerId, 'kind' => 'banner_click',
            'count' => 25, 'created_at' => now(), 'updated_at' => now(),
        ],
    ]);

    $figures = app(PortalReportingService::class)->derivedFigures([]);

    expect($figures['most_viewed_objects'][0])->toMatchArray(['name' => 'Most Viewed Object', 'views' => 7])
        ->and($figures['most_popular_categories'][0])->toMatchArray(['name' => 'Accommodation', 'views' => 10])
        ->and($figures['banner_click_through_rate'])->toBe(0.25);
});

it('counts new owners, new objects, bumps, published promotions, and pending moderation, period-scoped', function (): void {
    $geo = portalReportingFixture();

    $placementTierId = DB::table('placement_tiers')->insertGetId([
        'rank' => 1, 'border_colour' => '#000000', 'badge_colour' => '#111111',
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $placementPackageId = DB::table('placement_packages')->insertGetId([
        'placement_tier_id' => $placementTierId, 'price' => 1, 'currency' => 'EUR',
        'validity_days' => 30, 'bump_allowed' => true, 'is_active' => true,
        'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);

    DB::table('bump_events')->insert([
        'object_id' => $geo['objectInPeriod'], 'placement_package_id' => $placementPackageId,
        'scope_type' => ObjectType::class, 'scope_id' => $geo['typeId'],
        'occurred_at' => now()->subDays(2), 'type' => 'free',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('bump_events')->insert([
        'object_id' => $geo['objectOutsidePeriod'], 'placement_package_id' => $placementPackageId,
        'scope_type' => ObjectType::class, 'scope_id' => $geo['typeId'],
        'occurred_at' => now()->subDays(60), 'type' => 'free',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    DB::table('promotions')->insert([
        'object_id' => $geo['objectInPeriod'], 'territory_id' => $geo['territoryId'],
        'starts_at' => now()->subDays(2)->toDateString(), 'ends_at' => now()->addDays(10)->toDateString(),
        'status' => 'published', 'moderation_status' => 'approved', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('promotions')->insert([
        'object_id' => $geo['objectOutsidePeriod'], 'territory_id' => $geo['territoryId'],
        'starts_at' => now()->subDays(2)->toDateString(), 'ends_at' => now()->addDays(10)->toDateString(),
        'status' => 'draft', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $submitter = User::factory()->create();

    DB::table('moderation_requests')->insert([
        'section' => 'object', 'target_type' => Object_::class, 'target_id' => $geo['objectInPeriod'],
        'proposed_data' => json_encode([]), 'submitted_by' => $submitter->id, 'decision' => 'pending',
        'submitted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('moderation_requests')->insert([
        'section' => 'object', 'target_type' => Object_::class, 'target_id' => $geo['objectOutsidePeriod'],
        'proposed_data' => json_encode([]), 'submitted_by' => $submitter->id, 'decision' => 'approved',
        'submitted_at' => now(), 'decided_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);

    $filters = ['period_from' => now()->subDays(10)->toDateString(), 'period_until' => now()->toDateString()];
    $figures = app(PortalReportingService::class)->derivedFigures($filters);

    expect($figures['new_owner_count'])->toBe(1)
        ->and($figures['new_object_count'])->toBe(1)
        ->and($figures['bump_count'])->toBe(1)
        ->and($figures['published_promotion_count'])->toBe(1)
        ->and($figures['pending_moderation_count'])->toBe(1);
});

it('never reads stat_events to compute any derived figure', function (): void {
    portalReportingFixture();

    DB::enableQueryLog();
    app(PortalReportingService::class)->derivedFigures([]);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();
    DB::flushQueryLog();

    foreach ($queries as $query) {
        expect($query['query'])->not->toContain('stat_events');
    }
});
