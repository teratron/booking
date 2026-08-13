<?php

declare(strict_types=1);

use App\Filament\Cabinet\Pages\Dashboard;
use App\Models\Object_;
use App\Models\User;
use App\Services\Cabinet\ObjectDashboardService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Cabinet Dashboard
|--------------------------------------------------------------------------
|
| The panel's landing screen for whichever object is currently selected as
| the Filament tenant. Every figure on it either reads a real relation
| (placement, package, tier) or a real aggregate (StatDaily) — nothing here
| is a widget stub waiting to be wired up later, so these tests seed real
| rows and assert on the values those rows actually produce, not on the
| absence of an error.
|
*/

/** @return array{languageId: int, countryId: int, territoryId: int, typeId: int} */
function cabinetDashboardGeography(): array
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

/** @param  array<string, mixed>  $overrides */
function cabinetDashboardMakeObject(array $fixture, int $ownerId, string $name, array $overrides = []): Object_
{
    $objectId = DB::table('objects')->insertGetId(array_merge([
        'ulid' => (string) Str::ulid(),
        'owner_id' => $ownerId,
        'object_type_id' => $fixture['typeId'],
        'territory_id' => $fixture['territoryId'],
        'country_id' => $fixture['countryId'],
        'status' => 'published',
        'moderation_status' => 'approved',
        'availability_status' => 'available',
        'created_at' => now(), 'updated_at' => now(),
    ], $overrides));

    DB::table('object_translations')->insert([
        'object_id' => $objectId, 'locale' => 'en', 'name' => $name,
        'slug' => Str::slug($name), 'needs_review' => false, 'published_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    /** @var Object_ $object */
    $object = Object_::query()->withUnmoderated()->findOrFail($objectId);

    return $object;
}

/** @return array{tierId: int, packageId: int} */
function cabinetDashboardMakePlacementPackage(int $typeId, string $tierLabel, string $packageName): array
{
    static $rank = 0;
    $rank++;

    $tierId = DB::table('placement_tiers')->insertGetId([
        'rank' => $rank, 'border_colour' => '#000000', 'badge_colour' => '#000000',
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('placement_tier_translations')->insert([
        'placement_tier_id' => $tierId, 'locale' => 'en', 'label' => $tierLabel, 'badge_text' => $tierLabel,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $packageId = DB::table('placement_packages')->insertGetId([
        'placement_tier_id' => $tierId, 'object_type_id' => $typeId,
        'price' => 10.00, 'currency' => 'EUR', 'validity_days' => 30,
        'bump_allowed' => false, 'is_active' => true, 'display_order' => $rank,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('placement_package_translations')->insert([
        'placement_package_id' => $packageId, 'locale' => 'en', 'name' => $packageName,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return compact('tierId', 'packageId');
}

function cabinetDashboardGrantPlacement(Object_ $object, int $packageId, string $endsAt): void
{
    DB::table('object_placements')->insert([
        'object_id' => $object->id, 'placement_package_id' => $packageId,
        'starts_at' => now()->subDays(1)->toDateString(), 'ends_at' => $endsAt,
        'internal_priority' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** @return array{whatsapp: int, viber: int, website: int} */
function cabinetDashboardSeedContactChannelTypes(): array
{
    $ids = [];

    foreach (['whatsapp', 'viber', 'website'] as $order => $key) {
        $ids[$key] = DB::table('contact_channel_types')->insertGetId([
            'key' => $key, 'is_active' => true, 'display_order' => $order + 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    return $ids;
}

function cabinetDashboardSeedPageViews(Object_ $object): void
{
    $rows = [
        ['date' => now()->toDateString(), 'count' => 3],
        ['date' => now()->subDays(3)->toDateString(), 'count' => 2],
        ['date' => now()->subDays(20)->toDateString(), 'count' => 4],
        ['date' => now()->subDays(60)->toDateString(), 'count' => 1],
    ];

    foreach ($rows as $row) {
        DB::table('stat_dailies')->insert([
            'date' => $row['date'], 'subject_type' => Object_::class, 'subject_id' => $object->id,
            'kind' => 'object_page_view', 'count' => $row['count'],
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}

/** @param  array{whatsapp: int, viber: int, website: int}  $channelIds */
function cabinetDashboardSeedContactClicks(Object_ $object, array $channelIds): void
{
    $rows = [
        ['channel' => $channelIds['whatsapp'], 'count' => 2],
        ['channel' => $channelIds['viber'], 'count' => 3],
        ['channel' => $channelIds['website'], 'count' => 7],
    ];

    foreach ($rows as $row) {
        DB::table('stat_dailies')->insert([
            'date' => now()->toDateString(), 'subject_type' => Object_::class, 'subject_id' => $object->id,
            'kind' => 'contact_click', 'contact_channel_type_id' => $row['channel'], 'count' => $row['count'],
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}

function cabinetDashboardOwner(string $roleKey): User
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

it('renders for an authenticated owner with a selected tenant object, showing the object and its placement standing', function (): void {
    $fixture = cabinetDashboardGeography();
    $owner = cabinetDashboardOwner('dashboard_owner_render');
    $object = cabinetDashboardMakeObject($fixture, $owner->id, 'Seaside Villa');
    $placement = cabinetDashboardMakePlacementPackage($fixture['typeId'], 'Premium', 'Premium package');
    cabinetDashboardGrantPlacement($object, $placement['packageId'], now()->addDays(45)->toDateString());

    $response = $this->actingAs($owner)
        ->followingRedirects()
        ->get('/'.config('booking.panels.cabinet.path'));

    $response->assertSuccessful()
        ->assertSee('Seaside Villa')
        ->assertSee('Premium package')
        ->assertSee('Premium')
        ->assertSee(__('panel.objects.availability.available'));
});

it('reads real StatDaily rows for view and contact-click counts, not zero', function (): void {
    $fixture = cabinetDashboardGeography();
    $owner = cabinetDashboardOwner('dashboard_owner_stats');
    $object = cabinetDashboardMakeObject($fixture, $owner->id, 'Mountain Lodge');
    $channelIds = cabinetDashboardSeedContactChannelTypes();
    cabinetDashboardSeedPageViews($object);
    cabinetDashboardSeedContactClicks($object, $channelIds);

    $summary = app(ObjectDashboardService::class)->summarize($object);

    expect($summary->viewsToday)->toBe(3)
        ->and($summary->viewsThisWeek)->toBe(5)
        ->and($summary->viewsThisMonth)->toBe(9)
        ->and($summary->viewsAllTime)->toBe(10)
        ->and($summary->messengerClicks)->toBe(5)
        ->and($summary->websiteClicks)->toBe(7);
});

it('reads zero counts honestly for an object with no recorded activity yet', function (): void {
    $fixture = cabinetDashboardGeography();
    $owner = cabinetDashboardOwner('dashboard_owner_no_activity');
    $object = cabinetDashboardMakeObject($fixture, $owner->id, 'Untouched Chalet');

    $summary = app(ObjectDashboardService::class)->summarize($object);

    expect($summary->viewsToday)->toBe(0)
        ->and($summary->viewsAllTime)->toBe(0)
        ->and($summary->messengerClicks)->toBe(0)
        ->and($summary->websiteClicks)->toBe(0)
        ->and($summary->packageName)->toBeNull()
        ->and($summary->catalogPosition)->toBeNull();
});

it('surfaces an expiry warning for a placement ending inside the configured warning window', function (): void {
    $fixture = cabinetDashboardGeography();
    $owner = cabinetDashboardOwner('dashboard_owner_expiring');
    $object = cabinetDashboardMakeObject($fixture, $owner->id, 'Riverside Inn');
    $placement = cabinetDashboardMakePlacementPackage($fixture['typeId'], 'Business', 'Business package');
    // The widest configured warning offset is 30 days; 5 days out sits
    // comfortably inside that window.
    cabinetDashboardGrantPlacement($object, $placement['packageId'], now()->addDays(5)->toDateString());

    $summary = app(ObjectDashboardService::class)->summarize($object);

    expect($summary->expiryWarningActive)->toBeTrue()
        ->and($summary->expiryWarningDaysRemaining)->toBe(5);

    $response = $this->actingAs($owner)->followingRedirects()->get('/'.config('booking.panels.cabinet.path'));
    $response->assertSuccessful()->assertSee(__('panel.cabinet.dashboard.expiry_warning_days', ['days' => 5]));
});

it('does not surface an expiry warning for a placement ending outside the configured warning window', function (): void {
    $fixture = cabinetDashboardGeography();
    $owner = cabinetDashboardOwner('dashboard_owner_not_expiring');
    $object = cabinetDashboardMakeObject($fixture, $owner->id, 'Harbor Suites');
    $placement = cabinetDashboardMakePlacementPackage($fixture['typeId'], 'VIP', 'VIP package');
    // Well beyond the widest configured warning offset (30 days).
    cabinetDashboardGrantPlacement($object, $placement['packageId'], now()->addDays(90)->toDateString());

    $summary = app(ObjectDashboardService::class)->summarize($object);

    expect($summary->expiryWarningActive)->toBeFalse()
        ->and($summary->expiryWarningDaysRemaining)->toBe(90);

    $response = $this->actingAs($owner)->followingRedirects()->get('/'.config('booking.panels.cabinet.path'));
    $response->assertSuccessful()->assertDontSee(__('panel.cabinet.dashboard.expiry_warning_days', ['days' => 90]));
});

it('does not surface an expiry warning once the placement has already lapsed', function (): void {
    $fixture = cabinetDashboardGeography();
    $owner = cabinetDashboardOwner('dashboard_owner_lapsed');
    $object = cabinetDashboardMakeObject($fixture, $owner->id, 'Lapsed Lodge');
    $placement = cabinetDashboardMakePlacementPackage($fixture['typeId'], 'Standard', 'Free listing');
    cabinetDashboardGrantPlacement($object, $placement['packageId'], now()->subDays(2)->toDateString());

    $summary = app(ObjectDashboardService::class)->summarize($object);

    expect($summary->expiryWarningActive)->toBeFalse()
        ->and($summary->expiryWarningDaysRemaining)->toBeNull();
});

it('resolves a quick action to its real URL once the screen it targets already has a registered route', function (): void {
    $fixture = cabinetDashboardGeography();
    $owner = cabinetDashboardOwner('dashboard_owner_quick_actions');
    $object = cabinetDashboardMakeObject($fixture, $owner->id, 'Quick Actions Villa');

    // 'edit_object' and 'add_photos' now name real resource routes the
    // owner cabinet's object-management and photo-management tasks
    // registered — no stub needed for either any more, unlike the two
    // quick actions that still target screens no later task has built yet.
    // 'bump_object' stands in here for "a target that does not exist yet",
    // proving the same Route::has() gating this test always meant to prove,
    // on an action that still lacks one.
    $bumpRouteName = Dashboard::quickActionRouteName('bump_object');
    expect($bumpRouteName)->not->toBeNull();

    Route::get('/dashboard-test-only/bump-object', fn () => 'ok')->name($bumpRouteName);
    // A route named fluently (as above) is not indexed by name until the
    // router's name lookup table is rebuilt — normally a one-time step the
    // framework performs once after loading every route file at boot. A
    // route added after that point, as this test does, needs the same
    // rebuild explicitly or `route()`/`Route::has()` will not find it.
    Route::getRoutes()->refreshNameLookups();

    Filament::setCurrentPanel('cabinet');
    Filament::setTenant($object, isQuiet: true);

    $page = new Dashboard;
    $actions = collect($page->quickActions())->keyBy('key');

    expect($actions['bump_object']['url'])->toBe(route($bumpRouteName, ['tenant' => $object, 'record' => $object]))
        ->and($actions['edit_object']['url'])->toBe(
            route('filament.cabinet.resources.objects.edit', ['tenant' => $object, 'record' => $object])
        )
        ->and($actions['add_photos']['url'])->toBe(
            route('filament.cabinet.resources.photos.index', ['tenant' => $object, 'record' => $object])
        )
        // The remaining two quick actions have no registered target yet in
        // this codebase — they must resolve to null (a disabled action),
        // never to a broken link.
        ->and($actions['add_news']['url'])->toBeNull()
        ->and($actions['add_promotion']['url'])->toBeNull();
});

it('still renders successfully when some quick-action targets are missing', function (): void {
    $fixture = cabinetDashboardGeography();
    $owner = cabinetDashboardOwner('dashboard_owner_missing_targets');
    cabinetDashboardMakeObject($fixture, $owner->id, 'No Targets Yet Villa');

    $response = $this->actingAs($owner)->followingRedirects()->get('/'.config('booking.panels.cabinet.path'));

    $response->assertSuccessful()
        ->assertSee(__('panel.cabinet.dashboard.quick_actions.edit_object'))
        ->assertSee(__('panel.cabinet.dashboard.quick_actions.bump_object'))
        ->assertSee(__('panel.cabinet.dashboard.quick_actions.add_photos'))
        ->assertSee(__('panel.cabinet.dashboard.quick_actions.add_news'))
        ->assertSee(__('panel.cabinet.dashboard.quick_actions.add_promotion'));
});
