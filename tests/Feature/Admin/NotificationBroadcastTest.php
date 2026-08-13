<?php

declare(strict_types=1);

use App\Exceptions\BroadcastRateLimitedException;
use App\Filament\Admin\Pages\NotificationBroadcast;
use App\Models\Notification;
use App\Models\NotificationDispatch;
use App\Models\User;
use App\Services\Notifications\BroadcastComposer;
use App\Services\Notifications\NotificationDispatchService;
use App\Services\Settings\SettingsRepository;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\NotificationChannelSeeder;
use Database\Seeders\NotificationTemplateSeeder;
use Database\Seeders\NotificationTypeSeeder;
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
| Administrator Broadcast
|--------------------------------------------------------------------------
|
| Four claims matter: targeting by country, resort, or placement package
| reaches exactly the matching owners and de-duplicates an owner with
| several qualifying objects to a single message; sending is gated behind a
| confirmation naming the real resolved count; the daily rate limit is a
| visible refusal, never an unhandled exception, and resets on its own
| period; and every dispatch this produces is fully queryable through the
| shared notification tables.
|
*/

beforeEach(fn () => $this->seed([LanguageSeeder::class, NotificationChannelSeeder::class, NotificationTypeSeeder::class, NotificationTemplateSeeder::class]));

/**
 * @return array<string, int>
 */
function broadcastGeography(): array
{
    $languageId = DB::table('languages')->where('code', 'en')->value('id');

    $countryMd = DB::table('countries')->insertGetId([
        'code' => 'MD', 'currency' => 'MDL', 'phone_code' => '+373',
        'primary_language_id' => $languageId, 'is_active' => true, 'display_order' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $countryUa = DB::table('countries')->insertGetId([
        'code' => 'UA', 'currency' => 'UAH', 'phone_code' => '+380',
        'primary_language_id' => $languageId, 'is_active' => true, 'display_order' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $levelMd = DB::table('territory_levels')->insertGetId([
        'country_id' => $countryMd, 'depth_rank' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $levelUa = DB::table('territory_levels')->insertGetId([
        'country_id' => $countryUa, 'depth_rank' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $resortA = DB::table('territories')->insertGetId([
        'country_id' => $countryMd, 'level_id' => $levelMd, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $resortB = DB::table('territories')->insertGetId([
        'country_id' => $countryMd, 'level_id' => $levelMd, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $resortUa = DB::table('territories')->insertGetId([
        'country_id' => $countryUa, 'level_id' => $levelUa, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $typeStay = DB::table('object_types')->insertGetId([
        'key' => 'accommodation', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $tierId = DB::table('placement_tiers')->insertGetId([
        'rank' => 1, 'border_colour' => '#000000', 'badge_colour' => '#111111',
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $packageA = DB::table('placement_packages')->insertGetId([
        'placement_tier_id' => $tierId, 'price' => 10, 'currency' => 'EUR', 'validity_days' => 30,
        'bump_allowed' => false, 'is_active' => true, 'display_order' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $packageB = DB::table('placement_packages')->insertGetId([
        'placement_tier_id' => $tierId, 'price' => 20, 'currency' => 'EUR', 'validity_days' => 30,
        'bump_allowed' => false, 'is_active' => true, 'display_order' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return compact('countryMd', 'countryUa', 'resortA', 'resortB', 'resortUa', 'typeStay', 'packageA', 'packageB');
}

/**
 * @param  array<string, int>  $geo
 * @param  array<string, mixed>  $overrides
 */
function broadcastObject(array $geo, array $overrides = []): int
{
    $objectId = DB::table('objects')->insertGetId(array_merge([
        'ulid' => (string) Str::ulid(),
        'owner_id' => User::factory()->create()->id,
        'object_type_id' => $geo['typeStay'],
        'territory_id' => $geo['resortA'],
        'country_id' => $geo['countryMd'],
        'status' => 'published',
        'created_at' => now(), 'updated_at' => now(),
    ], $overrides));

    DB::table('object_translations')->insert([
        'object_id' => $objectId, 'locale' => 'en',
        'name' => 'Object #'.$objectId, 'slug' => 'object-'.$objectId,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return $objectId;
}

function broadcastPlacement(int $objectId, int $packageId): void
{
    DB::table('object_placements')->insert([
        'object_id' => $objectId, 'placement_package_id' => $packageId,
        'starts_at' => now(), 'ends_at' => now()->addDays(30),
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function broadcastActor(): User
{
    foreach (['admin_panel_access', 'notification.create'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $role = Role::findOrCreate('broadcast_sender', 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $role->syncPermissions(['admin_panel_access', 'notification.create']);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user->fresh();
}

/*
|--------------------------------------------------------------------------
| Targeting and de-duplication
|--------------------------------------------------------------------------
*/

it('reaches exactly the owner with a qualifying object in the targeted country, and no owner outside it', function (): void {
    $geo = broadcastGeography();
    $matchOwner = User::factory()->create();
    $otherCountryOwner = User::factory()->create();

    broadcastObject($geo, ['owner_id' => $matchOwner->id, 'country_id' => $geo['countryMd'], 'territory_id' => $geo['resortA']]);
    broadcastObject($geo, ['owner_id' => $otherCountryOwner->id, 'country_id' => $geo['countryUa'], 'territory_id' => $geo['resortUa']]);

    $actor = broadcastActor();

    $count = app(BroadcastComposer::class)->send(BroadcastComposer::TARGET_COUNTRY, $geo['countryMd'], 'Notice', 'Body', $actor);

    expect($count)->toBe(1)
        ->and(DB::table('notifications')->pluck('recipient_id')->all())->toBe([$matchOwner->id]);
});

it('reaches exactly the owner with a qualifying object in the targeted resort, and no owner in a sibling resort', function (): void {
    $geo = broadcastGeography();
    $matchOwner = User::factory()->create();
    $otherResortOwner = User::factory()->create();

    broadcastObject($geo, ['owner_id' => $matchOwner->id, 'territory_id' => $geo['resortA']]);
    broadcastObject($geo, ['owner_id' => $otherResortOwner->id, 'territory_id' => $geo['resortB']]);

    $actor = broadcastActor();

    $count = app(BroadcastComposer::class)->send(BroadcastComposer::TARGET_RESORT, $geo['resortA'], 'Notice', 'Body', $actor);

    expect($count)->toBe(1)
        ->and(DB::table('notifications')->pluck('recipient_id')->all())->toBe([$matchOwner->id]);
});

it('reaches exactly the owner whose current placement is in the targeted package, and no owner on another package', function (): void {
    $geo = broadcastGeography();
    $matchOwner = User::factory()->create();
    $otherPackageOwner = User::factory()->create();

    $matchObjectId = broadcastObject($geo, ['owner_id' => $matchOwner->id]);
    $otherObjectId = broadcastObject($geo, ['owner_id' => $otherPackageOwner->id]);

    broadcastPlacement($matchObjectId, $geo['packageA']);
    broadcastPlacement($otherObjectId, $geo['packageB']);

    $actor = broadcastActor();

    $count = app(BroadcastComposer::class)->send(BroadcastComposer::TARGET_PACKAGE, $geo['packageA'], 'Notice', 'Body', $actor);

    expect($count)->toBe(1)
        ->and(DB::table('notifications')->pluck('recipient_id')->all())->toBe([$matchOwner->id]);
});

it('sends exactly one message to an owner with two objects that both qualify for the same target', function (): void {
    $geo = broadcastGeography();
    $owner = User::factory()->create();

    broadcastObject($geo, ['owner_id' => $owner->id, 'country_id' => $geo['countryMd']]);
    broadcastObject($geo, ['owner_id' => $owner->id, 'country_id' => $geo['countryMd']]);

    $actor = broadcastActor();

    $count = app(BroadcastComposer::class)->send(BroadcastComposer::TARGET_COUNTRY, $geo['countryMd'], 'Notice', 'Body', $actor);

    expect($count)->toBe(1)
        ->and(DB::table('notifications')->where('recipient_id', $owner->id)->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Confirmation naming the real resolved count
|--------------------------------------------------------------------------
*/

it('halts sending behind a confirmation naming the exact resolved recipient count, without sending anything yet', function (): void {
    $geo = broadcastGeography();
    $owner1 = User::factory()->create();
    $owner2 = User::factory()->create();
    broadcastObject($geo, ['owner_id' => $owner1->id, 'country_id' => $geo['countryMd']]);
    broadcastObject($geo, ['owner_id' => $owner2->id, 'country_id' => $geo['countryMd']]);

    $actor = broadcastActor();

    $test = Livewire::actingAs($actor)
        ->test(NotificationBroadcast::class)
        ->fillForm([
            'target_type' => BroadcastComposer::TARGET_COUNTRY,
            'target_id' => $geo['countryMd'],
            'title' => 'Notice',
            'body' => 'Body text',
        ])
        ->mountAction('send')
        ->assertActionHalted('send');

    $mounted = $test->instance()->getMountedAction();

    expect($mounted)->not->toBeNull()
        ->and($mounted->isConfirmationRequired())->toBeTrue()
        ->and((string) $mounted->getModalDescription())->toContain('2');

    expect(DB::table('notifications')->count())->toBe(0);
});

it('sends to every resolved recipient once the confirmed action actually runs', function (): void {
    $geo = broadcastGeography();
    $owner1 = User::factory()->create();
    $owner2 = User::factory()->create();
    broadcastObject($geo, ['owner_id' => $owner1->id, 'country_id' => $geo['countryMd']]);
    broadcastObject($geo, ['owner_id' => $owner2->id, 'country_id' => $geo['countryMd']]);

    $actor = broadcastActor();

    Livewire::actingAs($actor)
        ->test(NotificationBroadcast::class)
        ->fillForm([
            'target_type' => BroadcastComposer::TARGET_COUNTRY,
            'target_id' => $geo['countryMd'],
            'title' => 'Notice',
            'body' => 'Body text',
        ])
        ->callAction('send');

    expect(DB::table('notifications')->count())->toBe(2)
        ->and(DB::table('audits')->where('event', 'administrator_broadcast_sent')->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Daily rate limit — visible refusal, never an unhandled exception
|--------------------------------------------------------------------------
*/

it('throws a typed, catchable refusal — never lets a broadcast partially send — once the daily limit is spent', function (): void {
    $geo = broadcastGeography();
    $owner = User::factory()->create();
    broadcastObject($geo, ['owner_id' => $owner->id, 'country_id' => $geo['countryMd']]);
    $actor = broadcastActor();

    app(SettingsRepository::class)->set('notifications.broadcast_rate_limit', 1);

    app(BroadcastComposer::class)->send(BroadcastComposer::TARGET_COUNTRY, $geo['countryMd'], 'First', 'Body', $actor);

    expect(fn () => app(BroadcastComposer::class)->send(BroadcastComposer::TARGET_COUNTRY, $geo['countryMd'], 'Second', 'Body', $actor))
        ->toThrow(BroadcastRateLimitedException::class);

    // The refused second attempt touched no recipient — only the first
    // broadcast's notification exists.
    expect(DB::table('notifications')->count())->toBe(1);
});

it('disables the send action in the compose screen once the daily limit is reached, and re-enables it once the period rolls over', function (): void {
    $geo = broadcastGeography();
    $owner = User::factory()->create();
    broadcastObject($geo, ['owner_id' => $owner->id, 'country_id' => $geo['countryMd']]);
    $actor = broadcastActor();

    app(SettingsRepository::class)->set('notifications.broadcast_rate_limit', 1);
    app(BroadcastComposer::class)->send(BroadcastComposer::TARGET_COUNTRY, $geo['countryMd'], 'First', 'Body', $actor);

    Livewire::actingAs($actor)
        ->test(NotificationBroadcast::class)
        ->assertActionDisabled('send');

    $this->travelTo(now()->addDay()->startOfDay()->addMinute());

    Livewire::actingAs($actor)
        ->test(NotificationBroadcast::class)
        ->assertActionEnabled('send');
});

it('sends again once the rate-limit period rolls over', function (): void {
    $geo = broadcastGeography();
    $owner = User::factory()->create();
    broadcastObject($geo, ['owner_id' => $owner->id, 'country_id' => $geo['countryMd']]);
    $actor = broadcastActor();

    app(SettingsRepository::class)->set('notifications.broadcast_rate_limit', 1);
    app(BroadcastComposer::class)->send(BroadcastComposer::TARGET_COUNTRY, $geo['countryMd'], 'First', 'Body', $actor);

    expect(app(BroadcastComposer::class)->isRateLimited())->toBeTrue();

    $this->travelTo(now()->addDay()->startOfDay()->addMinute());

    expect(app(BroadcastComposer::class)->isRateLimited())->toBeFalse();

    app(BroadcastComposer::class)->send(BroadcastComposer::TARGET_COUNTRY, $geo['countryMd'], 'Second', 'Body', $actor);

    expect(DB::table('notifications')->count())->toBe(2);
});

/*
|--------------------------------------------------------------------------
| Dispatch data — fully queryable after a send
|--------------------------------------------------------------------------
*/

it('leaves every dispatch fully queryable — recipient, body, date, delivery status, and read status', function (): void {
    $geo = broadcastGeography();
    $owner = User::factory()->create();
    broadcastObject($geo, ['owner_id' => $owner->id, 'country_id' => $geo['countryMd']]);
    $actor = broadcastActor();

    app(BroadcastComposer::class)->send(BroadcastComposer::TARGET_COUNTRY, $geo['countryMd'], 'Notice title', 'Notice body', $actor);

    $notification = Notification::query()->where('recipient_id', $owner->id)->firstOrFail();

    expect($notification->title)->toBe('Notice title')
        ->and($notification->body)->toBe('Notice body')
        ->and($notification->created_at)->not->toBeNull()
        ->and($notification->read_at)->toBeNull();

    $dispatch = NotificationDispatch::query()->where('notification_id', $notification->id)->firstOrFail();

    expect($dispatch->status)->toBe('sent');

    app(NotificationDispatchService::class)->markAsRead($notification);

    expect($notification->fresh()->read_at)->not->toBeNull();
});
