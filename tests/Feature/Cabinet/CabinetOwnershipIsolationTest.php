<?php

declare(strict_types=1);

use App\Filament\Cabinet\Resources\NewsItems\NewsItemResource;
use App\Filament\Cabinet\Resources\Notifications\NotificationResource;
use App\Filament\Cabinet\Resources\Objects\ObjectResource;
use App\Filament\Cabinet\Resources\Photos\PhotoResource;
use App\Filament\Cabinet\Resources\Promotions\PromotionResource;
use App\Filament\Cabinet\Resources\Reviews\ReviewResource;
use App\Filament\Cabinet\Resources\Rooms\RoomResource;
use App\Filament\Cabinet\Resources\Services\ServiceResource;
use App\Models\NewsItem;
use App\Models\Object_;
use App\Models\Promotion;
use App\Models\Room;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Cabinet Ownership Isolation Invariant
|--------------------------------------------------------------------------
|
| Every Track A-D task already proves ownership isolation for its own
| resource, in its own test file. What only this task can prove is that
| nothing was missed: it reads the panel's own resource registry
| (Filament::getPanel('cabinet')->getResources()) rather than an enumerated
| list, so a resource added later without this invariant holding is caught
| here without this file needing to change.
|
| Every tenant-scoped resource shares the exact same isolation mechanism —
| Filament's own tenancy (wired in CabinetPanelProvider), which refuses to
| resolve a {tenant} route parameter the acting user does not own before any
| resource-specific query ever runs. The one exception is the notification
| inbox, deliberately account-level rather than object-level, scoped by
| recipient_id instead.
|
*/

/** @return array{countryId: int, territoryId: int, typeId: int} */
function cabinetIsolationGeography(): array
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
    // has_rooms true — RoomResource is the one resource this file exercises
    // that is itself gated by the tenant's declared type capability, on top
    // of ownership; both fixtures below share this type so that gate never
    // interferes with the isolation check this file is actually after.
    $typeId = DB::table('object_types')->insertGetId([
        'key' => 'accommodation', 'is_active' => true, 'has_rooms' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return compact('countryId', 'territoryId', 'typeId');
}

function cabinetIsolationOwner(string $roleKey): User
{
    // The real object_owner role's own permission set (RoleSeeder), not an
    // inflated one — proving this invariant holds for the owner the cabinet
    // panel is actually built for.
    $permissions = ['object.view', 'object.edit', 'content.view', 'content.create', 'content.edit', 'cabinet_access'];

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $role = Role::findOrCreate($roleKey, 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $role->syncPermissions($permissions);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user->fresh();
}

function cabinetIsolationMakeObject(array $fixture, int $ownerId, string $name): Object_
{
    $objectId = DB::table('objects')->insertGetId([
        'ulid' => (string) Str::ulid(),
        'owner_id' => $ownerId,
        'object_type_id' => $fixture['typeId'],
        'territory_id' => $fixture['territoryId'],
        'country_id' => $fixture['countryId'],
        'status' => 'published', 'moderation_status' => 'approved',
        'latitude' => 45.1234500, 'longitude' => 28.1234500,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    DB::table('object_translations')->insert([
        'object_id' => $objectId, 'locale' => 'en', 'name' => $name,
        'slug' => Str::slug($name).'-'.$objectId, 'needs_review' => false, 'published_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    /** @var Object_ $object */
    $object = Object_::query()->withUnmoderated()->findOrFail($objectId);

    return $object;
}

function cabinetIsolationMakeRoom(Object_ $object): Room
{
    $id = DB::table('rooms')->insertGetId([
        'object_id' => $object->id, 'capacity' => 2, 'room_count' => 1,
        'display_order' => 0, 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return Room::query()->findOrFail($id);
}

function cabinetIsolationMakeNewsItem(Object_ $object): NewsItem
{
    $id = DB::table('news_items')->insertGetId([
        'author_id' => $object->owner_id, 'object_id' => $object->id, 'territory_id' => $object->territory_id,
        'status' => 'published', 'moderation_status' => 'approved',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return NewsItem::query()->findOrFail($id);
}

function cabinetIsolationMakePromotion(Object_ $object): Promotion
{
    $id = DB::table('promotions')->insertGetId([
        'object_id' => $object->id, 'territory_id' => $object->territory_id,
        'starts_at' => now()->toDateString(), 'ends_at' => now()->addDays(30)->toDateString(),
        'status' => 'published', 'moderation_status' => 'approved',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return Promotion::query()->findOrFail($id);
}

it('registers exactly the resources this phase built, every one tenant-scoped except the account-level notification inbox', function (): void {
    $resources = array_values(Filament::getPanel('cabinet')->getResources());

    expect($resources)->toEqualCanonicalizing([
        NewsItemResource::class,
        NotificationResource::class,
        ObjectResource::class,
        PhotoResource::class,
        PromotionResource::class,
        ReviewResource::class,
        RoomResource::class,
        ServiceResource::class,
    ]);

    foreach ($resources as $resourceClass) {
        expect($resourceClass::isScopedToTenant())->toBe($resourceClass !== NotificationResource::class);
    }
});

it("refuses every tenant-scoped resource's list route when the request's own tenant belongs to another owner, not spot-checked on a subset", function (): void {
    $fixture = cabinetIsolationGeography();
    $ownerA = cabinetIsolationOwner('isolation_owner_list_a');
    $ownerB = cabinetIsolationOwner('isolation_owner_list_b');
    $objectA = cabinetIsolationMakeObject($fixture, $ownerA->id, 'Owner A Villa');
    $objectB = cabinetIsolationMakeObject($fixture, $ownerB->id, 'Owner B Villa');

    $tenantScopedResources = collect(Filament::getPanel('cabinet')->getResources())
        ->reject(fn (string $resourceClass): bool => $resourceClass === NotificationResource::class)
        ->values();

    expect($tenantScopedResources)->not->toBeEmpty();

    foreach ($tenantScopedResources as $resourceClass) {
        // Refused as not-found, not forbidden: owner B's object is genuinely
        // outside what owner A's session can resolve as a tenant at all, the
        // same distinction the admin panel's own scope-mismatch tests draw.
        test()->actingAs($ownerA)
            ->get($resourceClass::getUrl('index', panel: 'cabinet', tenant: $objectB))
            ->assertNotFound();

        test()->actingAs($ownerA)
            ->get($resourceClass::getUrl('index', panel: 'cabinet', tenant: $objectA))
            ->assertSuccessful();
    }
});

it("refuses another owner's child row on an edit route even while the request's own tenant is genuinely valid", function (): void {
    $fixture = cabinetIsolationGeography();
    $ownerA = cabinetIsolationOwner('isolation_owner_child_a');
    $ownerB = cabinetIsolationOwner('isolation_owner_child_b');
    $objectA = cabinetIsolationMakeObject($fixture, $ownerA->id, 'Owner A Lodge');
    $objectB = cabinetIsolationMakeObject($fixture, $ownerB->id, 'Owner B Lodge');

    // ObjectResource and ServiceResource manage the tenant object itself —
    // their record IS the tenant, so the list-route sweep above already
    // exercises their isolation fully. These three manage a genuinely
    // distinct child row per tenant, the deeper case: a valid tenant whose
    // own scoped query must still refuse a record it does not itself own.
    $cases = [
        RoomResource::class => [cabinetIsolationMakeRoom($objectA), cabinetIsolationMakeRoom($objectB)],
        NewsItemResource::class => [cabinetIsolationMakeNewsItem($objectA), cabinetIsolationMakeNewsItem($objectB)],
        PromotionResource::class => [cabinetIsolationMakePromotion($objectA), cabinetIsolationMakePromotion($objectB)],
    ];

    foreach ($cases as $resourceClass => [$ownRecord, $foreignRecord]) {
        test()->actingAs($ownerA)
            ->get($resourceClass::getUrl('edit', ['record' => $foreignRecord], panel: 'cabinet', tenant: $objectA))
            ->assertNotFound();

        test()->actingAs($ownerA)
            ->get($resourceClass::getUrl('edit', ['record' => $ownRecord], panel: 'cabinet', tenant: $objectA))
            ->assertSuccessful();
    }
});

it("scopes the notification inbox to the recipient's own rows — the one cabinet resource not scoped by tenant at all", function (): void {
    $fixture = cabinetIsolationGeography();
    $ownerA = cabinetIsolationOwner('isolation_owner_notification_a');
    $ownerB = cabinetIsolationOwner('isolation_owner_notification_b');
    cabinetIsolationMakeObject($fixture, $ownerA->id, 'Owner A Cottage');
    cabinetIsolationMakeObject($fixture, $ownerB->id, 'Owner B Cottage');

    $typeId = DB::table('notification_types')->insertGetId([
        'key' => 'isolation_probe_type', 'class' => 'optional',
        'default_channels' => json_encode(['inbox']),
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $notificationA = DB::table('notifications')->insertGetId([
        'recipient_id' => $ownerA->id, 'notification_type_id' => $typeId,
        'title' => 'For owner A', 'body' => '', 'locale' => 'en',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('notifications')->insert([
        'recipient_id' => $ownerB->id, 'notification_type_id' => $typeId,
        'title' => 'For owner B', 'body' => '', 'locale' => 'en',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    test()->actingAs($ownerA);

    expect(NotificationResource::getEloquentQuery()->pluck('id')->all())->toBe([$notificationA]);
});
