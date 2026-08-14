<?php

declare(strict_types=1);

use App\Filament\Cabinet\Pages\Settings;
use App\Filament\Cabinet\Resources\Notifications\NotificationResource;
use App\Models\NotificationPreference;
use App\Models\NotificationType;
use App\Models\Object_;
use App\Models\User;
use App\Services\Notifications\NotificationDispatchService;
use App\Services\Notifications\NotificationPreferenceService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Cabinet Settings
|--------------------------------------------------------------------------
|
| Password and email change (with the base EditProfile class's own
| current-password confirmation), the owner's own interface locale — and
| that it is genuinely preferred by NotificationDispatchService once set,
| not merely stored — notification-preference toggles for optional-class
| types only, and the notification inbox's own read/unread state, all
| going through the same NotificationPreferenceService/
| NotificationDispatchService methods the dispatch pipeline itself uses.
|
*/

function cabinetSettingsGeography(): array
{
    $languageId = DB::table('languages')->insertGetId([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('languages')->insert([
        'code' => 'ru', 'short_label' => 'RU', 'is_active' => true, 'is_primary' => false,
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

function cabinetSettingsOwner(string $roleKey): User
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

function cabinetSettingsMakeObject(array $fixture, int $ownerId): Object_
{
    $object = new Object_;
    $object->ulid = (string) Str::ulid();
    $object->owner_id = $ownerId;
    $object->object_type_id = $fixture['typeId'];
    $object->territory_id = $fixture['territoryId'];
    $object->country_id = $fixture['countryId'];
    $object->status = 'draft';
    $object->save();

    return $object;
}

/** @return array<string, int> key => notification_type id */
function cabinetSettingsSeedNotificationTypes(): array
{
    $types = [
        ['key' => 'information_out_of_date', 'class' => 'optional'],
        ['key' => 'confirm_availability_status', 'class' => 'optional'],
        ['key' => 'placement_expiring', 'class' => 'transactional'],
    ];

    $ids = [];

    foreach ($types as $type) {
        $ids[$type['key']] = DB::table('notification_types')->insertGetId([
            'key' => $type['key'], 'class' => $type['class'],
            'default_channels' => json_encode(['inbox']),
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    return $ids;
}

it("changes the owner's own password and email, confirming the current password", function (): void {
    $fixture = cabinetSettingsGeography();
    $owner = cabinetSettingsOwner('settings_owner_password');
    $object = cabinetSettingsMakeObject($fixture, $owner->id);
    cabinetSettingsSeedNotificationTypes();

    Filament::setCurrentPanel('cabinet');
    Filament::setTenant($object, isQuiet: true);
    test()->actingAs($owner);

    Livewire::test(Settings::class)
        ->fillForm([
            'email' => 'new-address@example.test',
            'password' => 'a-brand-new-password',
            'passwordConfirmation' => 'a-brand-new-password',
            'currentPassword' => 'password',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $owner->refresh();

    expect($owner->email)->toBe('new-address@example.test')
        ->and(Hash::check('a-brand-new-password', $owner->password))->toBeTrue();
});

it("round-trips the owner's own locale, and NotificationDispatchService genuinely prefers it afterward", function (): void {
    $fixture = cabinetSettingsGeography();
    $owner = cabinetSettingsOwner('settings_owner_locale');
    $object = cabinetSettingsMakeObject($fixture, $owner->id);
    $typeIds = cabinetSettingsSeedNotificationTypes();

    Filament::setCurrentPanel('cabinet');
    Filament::setTenant($object, isQuiet: true);
    test()->actingAs($owner);

    Livewire::test(Settings::class)
        ->fillForm(['locale' => 'ru'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($owner->fresh()->locale)->toBe('ru');

    $type = NotificationType::query()->find($typeIds['placement_expiring']);
    $notification = app(NotificationDispatchService::class)->create($type, $owner->fresh());

    // Portal's primary language is 'en' — this is the falsifying half: it
    // proves the resolution genuinely reads the owner's own column rather
    // than coincidentally matching the portal default.
    expect($notification->locale)->toBe('ru');
});

it("falls back to the portal's primary language when no locale is set", function (): void {
    $fixture = cabinetSettingsGeography();
    $owner = cabinetSettingsOwner('settings_owner_no_locale');
    cabinetSettingsMakeObject($fixture, $owner->id);
    $typeIds = cabinetSettingsSeedNotificationTypes();

    $type = NotificationType::query()->find($typeIds['placement_expiring']);
    $notification = app(NotificationDispatchService::class)->create($type, $owner->fresh());

    expect($notification->locale)->toBe('en');
});

it('toggles notification preferences for optional-class types only, through the real preference service', function (): void {
    $fixture = cabinetSettingsGeography();
    $owner = cabinetSettingsOwner('settings_owner_preferences');
    $object = cabinetSettingsMakeObject($fixture, $owner->id);
    $typeIds = cabinetSettingsSeedNotificationTypes();

    Filament::setCurrentPanel('cabinet');
    Filament::setTenant($object, isQuiet: true);
    test()->actingAs($owner);

    $staleType = NotificationType::query()->find($typeIds['information_out_of_date']);
    $confirmType = NotificationType::query()->find($typeIds['confirm_availability_status']);

    Livewire::test(Settings::class)
        ->fillForm([
            "notification_preferences.{$staleType->id}" => false,
            "notification_preferences.{$confirmType->id}" => true,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $service = app(NotificationPreferenceService::class);

    expect($service->isEnabled($owner->fresh(), $staleType))->toBeFalse()
        ->and($service->isEnabled($owner->fresh(), $confirmType))->toBeTrue();

    // The save loop only ever iterates optional-class types (see
    // Settings::optionalNotificationTypes()) — a transactional type's
    // preference row is never written by it, proven directly rather than
    // introspecting the form's own component tree: even though nothing
    // submitted a value for it, no row exists for the transactional type at
    // all, confirming the form never offered it a key to submit against.
    expect(NotificationPreference::query()
        ->where('user_id', $owner->id)
        ->where('notification_type_id', $typeIds['placement_expiring'])
        ->exists())->toBeFalse();
});

it("lists only the owner's own notifications, with read/unread state toggled through the real dispatch service", function (): void {
    $fixture = cabinetSettingsGeography();
    $ownerA = cabinetSettingsOwner('settings_owner_inbox_a');
    $ownerB = cabinetSettingsOwner('settings_owner_inbox_b');
    $objectA = cabinetSettingsMakeObject($fixture, $ownerA->id);
    cabinetSettingsMakeObject($fixture, $ownerB->id);
    $typeIds = cabinetSettingsSeedNotificationTypes();

    $type = NotificationType::query()->find($typeIds['placement_expiring']);
    $dispatch = app(NotificationDispatchService::class);
    $ownNotification = $dispatch->create($type, $ownerA->fresh());
    $othersNotification = $dispatch->create($type, $ownerB->fresh());

    expect(Gate::forUser($ownerA)->allows('view', $ownNotification))->toBeTrue()
        ->and(Gate::forUser($ownerA)->allows('view', $othersNotification))->toBeFalse()
        ->and(Gate::forUser($ownerA)->allows('update', $othersNotification))->toBeFalse();

    expect($ownNotification->read_at)->toBeNull();

    $dispatch->markAsRead($ownNotification);
    expect($ownNotification->fresh()->read_at)->not->toBeNull();

    $dispatch->markAsUnread($ownNotification->fresh());
    expect($ownNotification->fresh()->read_at)->toBeNull();

    Filament::setCurrentPanel('cabinet');
    Filament::setTenant($objectA, isQuiet: true);
    test()->actingAs($ownerA);

    expect(NotificationResource::getEloquentQuery()->pluck('id')->all())->toBe([$ownNotification->id]);
});
