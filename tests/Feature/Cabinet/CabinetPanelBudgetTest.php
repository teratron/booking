<?php

declare(strict_types=1);

use App\Filament\Cabinet\Pages\Statistics;
use App\Models\Object_;
use App\Models\Room;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Cabinet Panel Query Budget Under Seeded Volume
|--------------------------------------------------------------------------
|
| The admin panel's own query-budget test seeds portal-wide DemoVolumeSeeder
| scale because its resources see the whole portal. The
| cabinet panel never does — every resource here is scoped to one owner's
| one object, so the volume dimension that matters is realistic per-owner
| content: a busy object with a full room list, a full gallery, a season's
| worth of reviews, and a history of news and promotions — not another
| thousand unrelated owners' rows a single owner's cabinet never lists
| anyway. Tagged `slow` for the same reason the admin equivalent is: it
| seeds real rows before measuring anything, so it is excluded from
| `composer test`/`quality` and run explicitly via `composer test:slow`.
|
*/

/** @return array{countryId: int, territoryId: int, typeId: int} */
function panelBudgetGeography(): array
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
        'key' => 'accommodation', 'is_active' => true, 'has_rooms' => true, 'has_availability_status' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return compact('countryId', 'territoryId', 'typeId');
}

function panelBudgetOwner(): User
{
    $permissions = ['object.view', 'object.edit', 'content.view', 'content.create', 'content.edit', 'cabinet_access'];

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $role = Role::findOrCreate('panel_budget_owner', 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $role->syncPermissions($permissions);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user->fresh();
}

/** @param  array{countryId: int, territoryId: int, typeId: int}  $fixture */
function panelBudgetMakeObject(array $fixture, int $ownerId): Object_
{
    $objectId = DB::table('objects')->insertGetId([
        'ulid' => (string) Str::ulid(),
        'owner_id' => $ownerId,
        'object_type_id' => $fixture['typeId'],
        'territory_id' => $fixture['territoryId'],
        'country_id' => $fixture['countryId'],
        'status' => 'published', 'moderation_status' => 'approved',
        'availability_status' => 'available',
        'latitude' => 45.1234500, 'longitude' => 28.1234500,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    DB::table('object_translations')->insert([
        'object_id' => $objectId, 'locale' => 'en', 'name' => 'Budget Test Villa',
        'slug' => 'budget-test-villa-'.$objectId, 'needs_review' => false, 'published_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    /** @var Object_ $object */
    $object = Object_::query()->withUnmoderated()->findOrFail($objectId);

    return $object;
}

function panelBudgetSeedRooms(Object_ $object, int $count): void
{
    for ($i = 1; $i <= $count; $i++) {
        $roomId = DB::table('rooms')->insertGetId([
            'object_id' => $object->id, 'capacity' => 2, 'room_count' => 1,
            'display_order' => $i, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('room_translations')->insert([
            'room_id' => $roomId, 'locale' => 'en', 'name' => "Room {$i}",
            'needs_review' => false, 'published_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('prices')->insert([
            'priceable_type' => Room::class, 'priceable_id' => $roomId,
            'calculation_unit' => 'per_night', 'amount' => 50 + $i, 'currency' => 'EUR',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}

function panelBudgetSeedPhotos(Object_ $object, int $count): void
{
    Storage::fake('public');

    for ($i = 1; $i <= $count; $i++) {
        $object->addMedia(UploadedFile::fake()->image("photo-{$i}.jpg", 1200, 800))
            ->toMediaCollection('photos');
    }
}

function panelBudgetSeedReviews(Object_ $object, int $count): void
{
    for ($i = 1; $i <= $count; $i++) {
        DB::table('reviews')->insert([
            'object_id' => $object->id, 'rating' => 4, 'body' => "Review body {$i}.",
            'status' => 'published', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}

function panelBudgetSeedNewsItems(Object_ $object, int $count): void
{
    for ($i = 1; $i <= $count; $i++) {
        $newsId = DB::table('news_items')->insertGetId([
            'author_id' => $object->owner_id, 'object_id' => $object->id, 'territory_id' => $object->territory_id,
            'status' => 'published', 'moderation_status' => 'approved',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('news_translations')->insert([
            'news_item_id' => $newsId, 'locale' => 'en', 'title' => "News {$i}", 'body' => "News body {$i}.",
            'slug' => "news-{$i}-{$newsId}", 'needs_review' => false, 'published_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}

function panelBudgetSeedPromotions(Object_ $object, int $count): void
{
    for ($i = 1; $i <= $count; $i++) {
        $promotionId = DB::table('promotions')->insertGetId([
            'object_id' => $object->id, 'territory_id' => $object->territory_id,
            'starts_at' => now()->toDateString(), 'ends_at' => now()->addDays(30)->toDateString(),
            'status' => 'published', 'moderation_status' => 'approved',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('promotion_translations')->insert([
            'promotion_id' => $promotionId, 'locale' => 'en', 'title' => "Promotion {$i}",
            'slug' => "promotion-{$i}-{$promotionId}", 'needs_review' => false, 'published_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}

function panelBudgetSeedNotifications(User $owner, int $count): void
{
    $typeId = DB::table('notification_types')->insertGetId([
        'key' => 'panel_budget_probe_type', 'class' => 'optional',
        'default_channels' => json_encode(['inbox']),
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    for ($i = 1; $i <= $count; $i++) {
        DB::table('notifications')->insert([
            'recipient_id' => $owner->id, 'notification_type_id' => $typeId,
            'title' => "Notification {$i}", 'body' => '', 'locale' => 'en',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}

function panelBudgetSeedStatistics(Object_ $object): void
{
    for ($day = 0; $day < 14; $day++) {
        DB::table('stat_dailies')->insert([
            'date' => now()->subDays($day)->toDateString(),
            'subject_type' => Object_::class, 'subject_id' => $object->id,
            'kind' => 'object_page_view', 'count' => 10 + $day,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}

/** @return array{status: int, queries: int} */
function measureCabinetPage(User $actor, string $url): array
{
    Cache::flush();
    DB::flushQueryLog();
    DB::enableQueryLog();

    $response = test()->actingAs($actor)->get($url);

    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();
    DB::flushQueryLog();

    return ['status' => $response->getStatusCode(), 'queries' => $queries];
}

it('resolves every cabinet resource list, the dashboard, and statistics within the query budget at realistic per-owner volume', function (): void {
    $fixture = panelBudgetGeography();
    $owner = panelBudgetOwner();
    $object = panelBudgetMakeObject($fixture, $owner->id);

    // A handful, not DemoVolumeSeeder's portal-wide scale — every cabinet
    // resource narrows to this one owner's one object regardless of how
    // many other owners exist, so the volume that could expose an N+1 here
    // is this object's own content, not the portal's.
    panelBudgetSeedRooms($object, 15);
    panelBudgetSeedPhotos($object, 20);
    panelBudgetSeedReviews($object, 15);
    panelBudgetSeedNewsItems($object, 10);
    panelBudgetSeedPromotions($object, 10);
    panelBudgetSeedNotifications($owner, 10);
    panelBudgetSeedStatistics($object);

    Filament::setCurrentPanel(Filament::getPanel('cabinet'));
    Filament::bootCurrentPanel();
    Filament::setTenant($object, isQuiet: true);

    $resources = Filament::getPanel('cabinet')->getResources();

    expect($resources)->not->toBeEmpty();

    $findings = [];

    foreach ($resources as $resourceClass) {
        $result = measureCabinetPage($owner, $resourceClass::getUrl('index', panel: 'cabinet', tenant: $object));
        $findings[$resourceClass] = $result;

        expect($result['status'])->toBe(200, "{$resourceClass}'s list page did not render successfully ({$result['status']}).");
        expect($result['queries'])->toBeLessThanOrEqual(30, "{$resourceClass}'s list page issued {$result['queries']} queries against a 30-query budget.");
    }

    $dashboard = measureCabinetPage($owner, '/'.config('booking.panels.cabinet.path').'/'.$object->id);
    $findings['dashboard'] = $dashboard;

    expect($dashboard['status'])->toBe(200)
        ->and($dashboard['queries'])->toBeLessThanOrEqual(30);

    $statistics = measureCabinetPage($owner, Statistics::getUrl(panel: 'cabinet', tenant: $object));
    $findings['statistics'] = $statistics;

    expect($statistics['status'])->toBe(200)
        ->and($statistics['queries'])->toBeLessThanOrEqual(30);

    // Findings are recorded as numbers, not a pass/fail claim — this line is
    // the task's own evidence, read from the actual run rather than
    // hand-typed into the phase file after the fact.
    fwrite(STDERR, "\n".json_encode(['seeded' => [
        'rooms' => 15, 'photos' => 20, 'reviews' => 15, 'news_items' => 10, 'promotions' => 10,
    ], 'findings' => $findings], JSON_PRETTY_PRINT)."\n");
})->group('slow');
