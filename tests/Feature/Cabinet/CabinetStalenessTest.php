<?php

declare(strict_types=1);

use App\Filament\Cabinet\Resources\Objects\ObjectResource;
use App\Filament\Cabinet\Resources\Objects\Pages\EditObject;
use App\Models\Object_;
use App\Models\User;
use App\Services\Cabinet\ObjectDashboardService;
use App\Services\Cabinet\ObjectStalenessService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Cabinet Staleness Surfacing
|--------------------------------------------------------------------------
|
| StalenessSweepJob (built earlier in this project) already detects a stale
| object and raises an `information_out_of_date` notification for it — this
| track adds no second detection mechanism, only a read of that existing
| state on the dashboard and the object's own edit screen. The flag is
| advisory only: it must never hide the object or block any cabinet action.
|
*/

/** @return array{countryId: int, territoryId: int, typeId: int} */
function cabinetStalenessGeography(): array
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
        'key' => 'accommodation', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return compact('countryId', 'territoryId', 'typeId');
}

function cabinetStalenessOwner(string $roleKey): User
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

/** @param  array<string, mixed>  $overrides */
function cabinetStalenessMakeObject(array $fixture, int $ownerId, array $overrides = []): Object_
{
    $objectId = DB::table('objects')->insertGetId(array_merge([
        'ulid' => (string) Str::ulid(),
        'owner_id' => $ownerId,
        'object_type_id' => $fixture['typeId'],
        'territory_id' => $fixture['territoryId'],
        'country_id' => $fixture['countryId'],
        'status' => 'published',
        'moderation_status' => 'approved',
        'latitude' => 45.1234500,
        'longitude' => 28.1234500,
        'created_at' => now()->subMonths(6), 'updated_at' => now()->subMonths(6),
    ], $overrides));

    DB::table('object_translations')->insert([
        'object_id' => $objectId, 'locale' => 'en', 'name' => 'Stale Villa',
        'slug' => 'stale-villa-'.$objectId, 'needs_review' => false, 'published_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    /** @var Object_ $object */
    $object = Object_::query()->withUnmoderated()->findOrFail($objectId);

    return $object;
}

function cabinetStalenessSeedNotificationType(): int
{
    return DB::table('notification_types')->insertGetId([
        'key' => 'information_out_of_date', 'class' => 'optional',
        'default_channels' => json_encode(['inbox']),
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function cabinetStalenessRaiseNotification(Object_ $object, int $typeId, int $recipientId, ?string $createdAt = null): void
{
    DB::table('notifications')->insert([
        'recipient_id' => $recipientId, 'notification_type_id' => $typeId,
        'related_type' => Object_::class, 'related_id' => $object->id,
        'title' => 'Your listing information may be out of date',
        'body' => '', 'locale' => 'en',
        'created_at' => $createdAt ?? now(), 'updated_at' => $createdAt ?? now(),
    ]);
}

it('surfaces the staleness notice on the dashboard for an object the sweep job already flagged', function (): void {
    $fixture = cabinetStalenessGeography();
    $owner = cabinetStalenessOwner('staleness_owner_dashboard_flagged');
    $object = cabinetStalenessMakeObject($fixture, $owner->id);
    $typeId = cabinetStalenessSeedNotificationType();
    cabinetStalenessRaiseNotification($object, $typeId, $owner->id);

    expect(app(ObjectDashboardService::class)->summarize($object->fresh())->isStale)->toBeTrue();

    $response = $this->actingAs($owner)
        ->followingRedirects()
        ->get('/'.config('booking.panels.cabinet.path'));

    $response->assertSuccessful()->assertSee(__('panel.cabinet.dashboard.staleness_notice'));
});

it('does not surface a staleness notice for an object the sweep job has never flagged', function (): void {
    $fixture = cabinetStalenessGeography();
    $owner = cabinetStalenessOwner('staleness_owner_dashboard_clean');
    $object = cabinetStalenessMakeObject($fixture, $owner->id);
    cabinetStalenessSeedNotificationType();

    expect(app(ObjectDashboardService::class)->summarize($object->fresh())->isStale)->toBeFalse();

    $response = $this->actingAs($owner)
        ->followingRedirects()
        ->get('/'.config('booking.panels.cabinet.path'));

    $response->assertSuccessful()->assertDontSee(__('panel.cabinet.dashboard.staleness_notice'));
});

it("surfaces the same staleness notice on the object's own edit screen, not only in the notification inbox", function (): void {
    $fixture = cabinetStalenessGeography();
    $owner = cabinetStalenessOwner('staleness_owner_edit_screen');
    $object = cabinetStalenessMakeObject($fixture, $owner->id);
    $typeId = cabinetStalenessSeedNotificationType();
    cabinetStalenessRaiseNotification($object, $typeId, $owner->id);

    $response = $this->actingAs($owner)
        ->followingRedirects()
        ->get(ObjectResource::getUrl('edit', ['record' => $object], panel: 'cabinet', tenant: $object));

    $response->assertSuccessful()
        ->assertSee(__('panel.cabinet.objects.staleness.title'))
        ->assertSee(__('panel.cabinet.objects.staleness.notice'));
});

it('clears the flag once the object has been edited since the notification was raised, rather than nagging forever', function (): void {
    $fixture = cabinetStalenessGeography();
    $owner = cabinetStalenessOwner('staleness_owner_clears_after_edit');
    $object = cabinetStalenessMakeObject($fixture, $owner->id);
    $typeId = cabinetStalenessSeedNotificationType();

    // The notification predates the object's current version — the owner
    // has already updated the listing since the sweep job raised it, so the
    // flag must not still claim the information is out of date.
    cabinetStalenessRaiseNotification($object, $typeId, $owner->id, now()->subDays(10)->toDateTimeString());
    DB::table('objects')->where('id', $object->id)->update(['updated_at' => now()->subDays(1)]);

    expect(app(ObjectStalenessService::class)->isFlagged($object->fresh()))->toBeFalse();
});

it('never blocks the save action for a flagged object — the flag is advisory only', function (): void {
    $fixture = cabinetStalenessGeography();
    $owner = cabinetStalenessOwner('staleness_owner_advisory_only');
    // A draft object's edits always apply directly (T-4B01) — kept that way
    // here deliberately, so this test proves only the staleness flag's own
    // advisory nature, not an unrelated moderation-routing decision.
    $object = cabinetStalenessMakeObject($fixture, $owner->id, ['status' => 'draft', 'moderation_status' => null]);
    $typeId = cabinetStalenessSeedNotificationType();
    cabinetStalenessRaiseNotification($object, $typeId, $owner->id);

    expect(app(ObjectStalenessService::class)->isFlagged($object->fresh()))->toBeTrue();

    Filament::setCurrentPanel(Filament::getPanel('cabinet'));
    Filament::setTenant($object, isQuiet: true);

    Livewire::actingAs($owner)
        ->test(EditObject::class, ['record' => $object->getKey()])
        ->fillForm(['address' => 'Updated Address 12'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(DB::table('objects')->where('id', $object->id)->value('address'))->toBe('Updated Address 12');
});
