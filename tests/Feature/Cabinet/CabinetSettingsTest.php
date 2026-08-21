<?php

declare(strict_types=1);

use App\Filament\Cabinet\Pages\Settings;
use App\Filament\Cabinet\Resources\Notifications\NotificationResource;
use App\Filament\Cabinet\Resources\Notifications\Pages\ListNotifications;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\NotificationType;
use App\Models\Object_;
use App\Models\User;
use App\Services\Notifications\NotificationDispatchService;
use App\Services\Notifications\NotificationPreferenceService;
use Filament\Actions\Exceptions\ActionNotResolvableException;
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

it("renders the inbox table newest-first and excludes another owner's rows entirely", function (): void {
    $fixture = cabinetSettingsGeography();
    $ownerA = cabinetSettingsOwner('settings_owner_table_order_a');
    $ownerB = cabinetSettingsOwner('settings_owner_table_order_b');
    $objectA = cabinetSettingsMakeObject($fixture, $ownerA->id);
    cabinetSettingsMakeObject($fixture, $ownerB->id);
    $typeIds = cabinetSettingsSeedNotificationTypes();

    $olderId = DB::table('notifications')->insertGetId([
        'recipient_id' => $ownerA->id, 'notification_type_id' => $typeIds['placement_expiring'],
        'title' => 'Older notice', 'body' => 'First received.', 'locale' => 'en',
        'created_at' => now()->subDay(), 'updated_at' => now()->subDay(),
    ]);
    $newerId = DB::table('notifications')->insertGetId([
        'recipient_id' => $ownerA->id, 'notification_type_id' => $typeIds['placement_expiring'],
        'title' => 'Newer notice', 'body' => 'Received just now.', 'locale' => 'en',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $foreignId = DB::table('notifications')->insertGetId([
        'recipient_id' => $ownerB->id, 'notification_type_id' => $typeIds['placement_expiring'],
        'title' => "Owner B's own notice", 'body' => 'Not for owner A.', 'locale' => 'en',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $older = Notification::query()->findOrFail($olderId);
    $newer = Notification::query()->findOrFail($newerId);
    $foreign = Notification::query()->findOrFail($foreignId);

    Filament::setCurrentPanel('cabinet');
    Filament::setTenant($objectA, isQuiet: true);
    test()->actingAs($ownerA);

    Livewire::test(ListNotifications::class)
        ->assertCanSeeTableRecords([$newer, $older], inOrder: true)
        ->assertCanNotSeeTableRecords([$foreign]);
});

it("formats the unread/read badge and toggles it in both directions through the inbox's own action, using the real dispatch service", function (): void {
    $fixture = cabinetSettingsGeography();
    $owner = cabinetSettingsOwner('settings_owner_toggle_action');
    $object = cabinetSettingsMakeObject($fixture, $owner->id);
    $typeIds = cabinetSettingsSeedNotificationTypes();

    $notificationId = DB::table('notifications')->insertGetId([
        'recipient_id' => $owner->id, 'notification_type_id' => $typeIds['placement_expiring'],
        'title' => 'Your placement is expiring', 'body' => 'Renew before it lapses.', 'locale' => 'en',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $notification = Notification::query()->findOrFail($notificationId);

    Filament::setCurrentPanel('cabinet');
    Filament::setTenant($object, isQuiet: true);
    test()->actingAs($owner);

    // Unread state: the title column renders, the status badge reads
    // "unread", and the row action is labelled to mark it read — the badge
    // and the action label are both driven by the identical
    // read_at === null check inside NotificationsTable's own closures.
    Livewire::test(ListNotifications::class)
        ->assertTableColumnFormattedStateSet('title', 'Your placement is expiring', $notification)
        ->assertTableColumnFormattedStateSet('read_at', __('panel.cabinet.notifications.status.unread'), $notification)
        ->assertTableActionExists(
            'toggle_read',
            record: $notification,
            // A plain boolean, not expect()->toBe() — assertTableActionExists
            // passes this return value straight to Assert::assertTrue(),
            // which fails on the Pest\Expectation instance expect() returns
            // for chaining even when the comparison inside it holds.
            checkActionUsing: fn ($action): bool => $action->getLabel() === __('panel.cabinet.notifications.actions.mark_read'),
        )
        ->callTableAction('toggle_read', $notification)
        ->assertHasNoTableActionErrors();

    $notification->refresh();
    expect($notification->read_at)->not->toBeNull();

    // Read state: the same closures now resolve their other branch, wired
    // to NotificationDispatchService::markAsUnread on the next call.
    Livewire::test(ListNotifications::class)
        ->assertTableColumnFormattedStateSet('read_at', __('panel.cabinet.notifications.status.read'), $notification)
        ->assertTableActionExists(
            'toggle_read',
            record: $notification,
            checkActionUsing: fn ($action): bool => $action->getLabel() === __('panel.cabinet.notifications.actions.mark_unread'),
        )
        ->callTableAction('toggle_read', $notification)
        ->assertHasNoTableActionErrors();

    expect($notification->fresh()->read_at)->toBeNull();
});

it("refuses to toggle another owner's notification through the inbox's own action, since it never resolves within the recipient-scoped table query", function (): void {
    $fixture = cabinetSettingsGeography();
    $ownerA = cabinetSettingsOwner('settings_owner_toggle_cross_a');
    $ownerB = cabinetSettingsOwner('settings_owner_toggle_cross_b');
    $objectA = cabinetSettingsMakeObject($fixture, $ownerA->id);
    cabinetSettingsMakeObject($fixture, $ownerB->id);
    $typeIds = cabinetSettingsSeedNotificationTypes();

    $foreignId = DB::table('notifications')->insertGetId([
        'recipient_id' => $ownerB->id, 'notification_type_id' => $typeIds['placement_expiring'],
        'title' => "Owner B's notice", 'body' => 'Should stay untouched.', 'locale' => 'en',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $foreignNotification = Notification::query()->findOrFail($foreignId);

    Filament::setCurrentPanel('cabinet');
    Filament::setTenant($objectA, isQuiet: true);
    test()->actingAs($ownerA);

    $attempt = fn () => Livewire::test(ListNotifications::class)
        ->callTableAction('toggle_read', $foreignNotification);

    expect($attempt)->toThrow(ActionNotResolvableException::class);

    expect($foreignNotification->fresh()->read_at)->toBeNull();
});
