<?php

declare(strict_types=1);

use App\Jobs\AvailabilityConfirmationSweepJob;
use App\Jobs\DispatchRetryJob;
use App\Jobs\PlacementExpirySweepJob;
use App\Jobs\StalenessSweepJob;
use App\Models\ModerationRequest;
use App\Models\Notification;
use App\Models\NotificationDispatch;
use App\Models\NotificationType;
use App\Models\Object_;
use App\Models\User;
use App\Services\Audit\AuditJournal;
use App\Services\Moderation\ModerationDecisionService;
use App\Services\Notifications\BroadcastComposer;
use App\Services\Notifications\NotificationDispatchService;
use App\Services\Notifications\NotificationPreferenceService;
use App\Services\Objects\ObjectLifecycleService;
use App\Services\Placement\PlacementLifecycleService;
use App\Services\Settings\SettingsRepository;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\NotificationChannelSeeder;
use Database\Seeders\NotificationTemplateSeeder;
use Database\Seeders\NotificationTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Notification Delivery Completeness
|--------------------------------------------------------------------------
|
| The cross-track terminal check for the notification surface: every one of
| the ten registered notification types is proven, one at a time, against
| the real trigger its own seeded row names — not against a hand-rolled
| substitute. The three optional-class types additionally prove a disabled
| preference is recorded `suppressed`, never silently dropped, and every
| scheduled sweep with an idempotency guard proves a re-run against
| already-processed state raises nothing new.
|
*/

beforeEach(fn () => $this->seed([LanguageSeeder::class, NotificationChannelSeeder::class, NotificationTypeSeeder::class, NotificationTemplateSeeder::class]));

/** @return array{country: int, territory: int, type: int} */
function completenessGeography(): array
{
    $languageId = DB::table('languages')->where('code', 'en')->value('id');
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
        'key' => 'hotel', 'is_active' => true, 'has_availability_status' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return ['country' => $countryId, 'territory' => $territoryId, 'type' => $typeId];
}

/**
 * @param  array{country: int, territory: int, type: int}  $geo
 * @param  array<string, mixed>  $overrides
 */
function completenessObject(array $geo, array $overrides = []): int
{
    return DB::table('objects')->insertGetId(array_merge([
        'ulid' => (string) Str::ulid(),
        'owner_id' => User::factory()->create()->id,
        'object_type_id' => $geo['type'],
        'territory_id' => $geo['territory'],
        'country_id' => $geo['country'],
        'status' => 'published',
        'moderation_status' => 'approved',
        'availability_status' => 'available',
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));
}

/** @return array{tierId: int, packageId: int} */
function completenessPlacementPackage(int $rank, int $objectTypeId): array
{
    $tierId = DB::table('placement_tiers')->insertGetId([
        'rank' => $rank, 'border_colour' => '#000000', 'badge_colour' => '#111111',
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $packageId = DB::table('placement_packages')->insertGetId([
        'placement_tier_id' => $tierId, 'object_type_id' => $objectTypeId,
        'price' => $rank === 4 ? 0 : 50, 'currency' => 'EUR', 'validity_days' => 365,
        'bump_allowed' => false, 'is_active' => true, 'display_order' => $rank,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return ['tierId' => $tierId, 'packageId' => $packageId];
}

function completenessNotificationCount(int $objectId, string $typeKey): int
{
    return Notification::query()
        ->where('related_type', Object_::class)
        ->where('related_id', $objectId)
        ->whereHas('type', fn ($q) => $q->where('key', $typeKey))
        ->count();
}

/** @return array{object: Object_, objectId: int, countryId: int, owner: User} */
function completenessModerationFixture(): array
{
    $geo = completenessGeography();
    $owner = User::factory()->create();

    $objectId = DB::table('objects')->insertGetId([
        'ulid' => (string) Str::ulid(), 'owner_id' => $owner->id,
        'object_type_id' => $geo['type'], 'territory_id' => $geo['territory'], 'country_id' => $geo['country'],
        'status' => 'published', 'moderation_status' => 'approved', 'availability_status' => 'available',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('object_translations')->insert([
        'object_id' => $objectId, 'locale' => 'en', 'name' => 'Original Villa',
        'slug' => 'original-villa-'.$objectId, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return [
        'object' => Object_::query()->withUnmoderated()->findOrFail($objectId),
        'objectId' => $objectId,
        'countryId' => $geo['country'],
        'owner' => $owner,
    ];
}

/** @param  array{objectId: int, countryId: int, owner: User}  $fixture */
function completenessPendingModerationRequest(array $fixture): ModerationRequest
{
    return ModerationRequest::create([
        'target_type' => Object_::class,
        'target_id' => $fixture['objectId'],
        'country_id' => $fixture['countryId'],
        'owner_id' => $fixture['owner']->id,
        'section' => 'name',
        'previous_data' => ['name' => 'Original Villa'],
        'proposed_data' => ['name' => 'New Name'],
        'submitted_by' => $fixture['owner']->id,
        'submitted_at' => now(),
        'decision' => 'pending',
    ]);
}

/*
|--------------------------------------------------------------------------
| The registry itself
|--------------------------------------------------------------------------
*/

it('registers exactly the ten specified notification types, each with its expected class', function (): void {
    $types = DB::table('notification_types')->pluck('class', 'key');

    expect($types)->toHaveCount(10);

    $expected = [
        'placement_expiring' => 'transactional',
        'package_expired' => 'transactional',
        'moderation_approved' => 'transactional',
        'moderation_rejected' => 'transactional',
        'revision_requested' => 'transactional',
        'information_out_of_date' => 'optional',
        'confirm_availability_status' => 'optional',
        'administration_message' => 'optional',
        'object_status_changed' => 'transactional',
        'system_message' => 'transactional',
    ];

    foreach ($expected as $key => $class) {
        expect($types[$key] ?? null)->toBe($class);
    }
});

/*
|--------------------------------------------------------------------------
| placement_expiring / package_expired — PlacementExpirySweepJob
|--------------------------------------------------------------------------
*/

it('fires placement_expiring exactly once for a placement reaching a configured warning offset, and never duplicates on a same-day re-run', function (): void {
    $geo = completenessGeography();
    $package = completenessPlacementPackage(2, $geo['type']);
    $objectId = completenessObject($geo);

    DB::table('object_placements')->insert([
        'object_id' => $objectId, 'placement_package_id' => $package['packageId'],
        'starts_at' => now()->subDays(23)->toDateString(), 'ends_at' => now()->addDays(7)->toDateString(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $run = fn () => (new PlacementExpirySweepJob)->handle(
        app(PlacementLifecycleService::class),
        app(SettingsRepository::class),
        app(AuditJournal::class),
    );

    $run();

    expect(completenessNotificationCount($objectId, 'placement_expiring'))->toBe(1);

    // Idempotency check 1/4 — re-running against the same day must not duplicate.
    $run();

    expect(completenessNotificationCount($objectId, 'placement_expiring'))->toBe(1);
});

it('fires package_expired exactly once for an expired placement, and never duplicates on a re-run', function (): void {
    $geo = completenessGeography();
    $paid = completenessPlacementPackage(1, $geo['type']);
    completenessPlacementPackage(4, $geo['type']);
    $objectId = completenessObject($geo);

    DB::table('object_placements')->insert([
        'object_id' => $objectId, 'placement_package_id' => $paid['packageId'],
        'starts_at' => now()->subDays(31)->toDateString(), 'ends_at' => now()->subDay()->toDateString(),
        'created_at' => now()->subDays(31), 'updated_at' => now(),
    ]);

    $run = fn () => (new PlacementExpirySweepJob)->handle(
        app(PlacementLifecycleService::class),
        app(SettingsRepository::class),
        app(AuditJournal::class),
    );

    $run();

    expect(completenessNotificationCount($objectId, 'package_expired'))->toBe(1);

    // Idempotency check 2/4 — a re-run must not re-act on an object it already processed.
    $run();

    expect(completenessNotificationCount($objectId, 'package_expired'))->toBe(1);
});

/*
|--------------------------------------------------------------------------
| moderation_approved / moderation_rejected / revision_requested —
| ModerationDecisionService
|--------------------------------------------------------------------------
*/

it('fires moderation_approved exactly once, to the submitting owner, when a pending request is approved', function (): void {
    $fixture = completenessModerationFixture();
    $request = completenessPendingModerationRequest($fixture);
    $actor = User::factory()->create();

    app(ModerationDecisionService::class)->approve($request, $actor);

    $notifications = Notification::query()
        ->where('recipient_id', $fixture['owner']->id)
        ->whereHas('type', fn ($q) => $q->where('key', 'moderation_approved'))
        ->get();

    expect($notifications)->toHaveCount(1)
        ->and($notifications->first()->related_type)->toBe(ModerationRequest::class)
        ->and($notifications->first()->related_id)->toBe($request->id);
});

it('fires moderation_rejected exactly once, to the submitting owner, when a pending request is rejected', function (): void {
    $fixture = completenessModerationFixture();
    $request = completenessPendingModerationRequest($fixture);
    $actor = User::factory()->create();

    app(ModerationDecisionService::class)->reject($request, 'Not needed right now.', $actor);

    $notifications = Notification::query()
        ->where('recipient_id', $fixture['owner']->id)
        ->whereHas('type', fn ($q) => $q->where('key', 'moderation_rejected'))
        ->get();

    expect($notifications)->toHaveCount(1);
});

it('fires revision_requested exactly once, to the submitting owner, when a moderator requests revision on a pending request', function (): void {
    $fixture = completenessModerationFixture();
    $request = completenessPendingModerationRequest($fixture);
    $actor = User::factory()->create();

    app(ModerationDecisionService::class)->requestRevision($request, 'Please add more photos.', $actor);

    $notifications = Notification::query()
        ->where('recipient_id', $fixture['owner']->id)
        ->whereHas('type', fn ($q) => $q->where('key', 'revision_requested'))
        ->get();

    expect($notifications)->toHaveCount(1);
});

/*
|--------------------------------------------------------------------------
| information_out_of_date — StalenessSweepJob
|--------------------------------------------------------------------------
*/

it('fires information_out_of_date exactly once for a stale object, and never duplicates within the staleness window', function (): void {
    Queue::fake();
    $geo = completenessGeography();
    $objectId = completenessObject($geo, ['updated_at' => now()->subDays(200)]);

    $run = fn () => (new StalenessSweepJob)->handle(app(NotificationDispatchService::class), app(SettingsRepository::class));

    $run();

    expect(completenessNotificationCount($objectId, 'information_out_of_date'))->toBe(1);

    // Idempotency check 3/4.
    $run();

    expect(completenessNotificationCount($objectId, 'information_out_of_date'))->toBe(1);
});

it('records information_out_of_date as suppressed, not silently skipped, when the owner disables the preference', function (): void {
    Queue::fake();
    $geo = completenessGeography();
    $owner = User::factory()->create();
    completenessObject($geo, ['owner_id' => $owner->id, 'updated_at' => now()->subDays(200)]);

    $type = NotificationType::query()->where('key', 'information_out_of_date')->firstOrFail();
    app(NotificationPreferenceService::class)->setEnabled($owner, $type, false);

    (new StalenessSweepJob)->handle(app(NotificationDispatchService::class), app(SettingsRepository::class));

    $notification = Notification::query()->where('recipient_id', $owner->id)
        ->whereHas('type', fn ($q) => $q->where('key', 'information_out_of_date'))
        ->firstOrFail();

    $dispatch = NotificationDispatch::query()->where('notification_id', $notification->id)->firstOrFail();

    expect($dispatch->status)->toBe('suppressed');
});

/*
|--------------------------------------------------------------------------
| confirm_availability_status — AvailabilityConfirmationSweepJob
|--------------------------------------------------------------------------
*/

it('fires confirm_availability_status exactly once for an unconfirmed object, and never duplicates within the cadence', function (): void {
    Queue::fake();
    $geo = completenessGeography();
    $objectId = completenessObject($geo, ['availability_last_confirmed_at' => now()->subDays(20)]);

    $run = fn () => (new AvailabilityConfirmationSweepJob)->handle(app(NotificationDispatchService::class), app(SettingsRepository::class));

    $run();

    expect(completenessNotificationCount($objectId, 'confirm_availability_status'))->toBe(1);

    // Idempotency check 4/4.
    $run();

    expect(completenessNotificationCount($objectId, 'confirm_availability_status'))->toBe(1);
});

it('records confirm_availability_status as suppressed, not silently skipped, when the owner disables the preference', function (): void {
    Queue::fake();
    $geo = completenessGeography();
    $owner = User::factory()->create();
    completenessObject($geo, ['owner_id' => $owner->id, 'availability_last_confirmed_at' => now()->subDays(20)]);

    $type = NotificationType::query()->where('key', 'confirm_availability_status')->firstOrFail();
    app(NotificationPreferenceService::class)->setEnabled($owner, $type, false);

    (new AvailabilityConfirmationSweepJob)->handle(app(NotificationDispatchService::class), app(SettingsRepository::class));

    $notification = Notification::query()->where('recipient_id', $owner->id)
        ->whereHas('type', fn ($q) => $q->where('key', 'confirm_availability_status'))
        ->firstOrFail();

    $dispatch = NotificationDispatch::query()->where('notification_id', $notification->id)->firstOrFail();

    expect($dispatch->status)->toBe('suppressed');
});

/*
|--------------------------------------------------------------------------
| administration_message — BroadcastComposer::send()
|--------------------------------------------------------------------------
*/

it('fires administration_message exactly once per resolved recipient via BroadcastComposer::send()', function (): void {
    $geo = completenessGeography();
    $owner = User::factory()->create();
    completenessObject($geo, ['owner_id' => $owner->id]);
    $actor = User::factory()->create();

    $count = app(BroadcastComposer::class)->send(BroadcastComposer::TARGET_COUNTRY, $geo['country'], 'Notice', 'Body', $actor);

    expect($count)->toBe(1);

    $notifications = Notification::query()
        ->where('recipient_id', $owner->id)
        ->whereHas('type', fn ($q) => $q->where('key', 'administration_message'))
        ->get();

    expect($notifications)->toHaveCount(1);
});

it('records administration_message as suppressed, not silently skipped, when the recipient disables the preference', function (): void {
    $geo = completenessGeography();
    $owner = User::factory()->create();
    completenessObject($geo, ['owner_id' => $owner->id]);
    $actor = User::factory()->create();

    $type = NotificationType::query()->where('key', 'administration_message')->firstOrFail();
    app(NotificationPreferenceService::class)->setEnabled($owner, $type, false);

    app(BroadcastComposer::class)->send(BroadcastComposer::TARGET_COUNTRY, $geo['country'], 'Notice', 'Body', $actor);

    $notification = Notification::query()->where('recipient_id', $owner->id)->firstOrFail();
    $dispatch = NotificationDispatch::query()->where('notification_id', $notification->id)->firstOrFail();

    expect($dispatch->status)->toBe('suppressed');
});

/*
|--------------------------------------------------------------------------
| object_status_changed — ObjectLifecycleService::publish() / hide()
|--------------------------------------------------------------------------
*/

it('fires object_status_changed exactly once, to the owner, when an administrator publishes an object', function (): void {
    $geo = completenessGeography();
    $owner = User::factory()->create();
    $actor = User::factory()->create();

    $object = new Object_;
    $object->ulid = (string) Str::ulid();
    $object->owner_id = $owner->id;
    $object->object_type_id = $geo['type'];
    $object->territory_id = $geo['territory'];
    $object->country_id = $geo['country'];
    $object->status = 'draft';
    $object->save();

    app(ObjectLifecycleService::class)->publish($object, $actor);

    $notifications = Notification::query()
        ->where('recipient_id', $owner->id)
        ->whereHas('type', fn ($q) => $q->where('key', 'object_status_changed'))
        ->get();

    expect($notifications)->toHaveCount(1)
        ->and($notifications->first()->related_type)->toBe(Object_::class)
        ->and($notifications->first()->related_id)->toBe($object->id);
});

it('fires object_status_changed exactly once, to the owner, when an administrator hides an object', function (): void {
    $geo = completenessGeography();
    $owner = User::factory()->create();
    $actor = User::factory()->create();

    $objectId = completenessObject($geo, ['owner_id' => $owner->id]);
    $object = Object_::query()->findOrFail($objectId);

    app(ObjectLifecycleService::class)->hide($object, $actor);

    expect(completenessNotificationCount($objectId, 'object_status_changed'))->toBe(1);

    $notifications = Notification::query()
        ->where('recipient_id', $owner->id)
        ->whereHas('type', fn ($q) => $q->where('key', 'object_status_changed'))
        ->get();

    expect($notifications)->toHaveCount(1);
});

it('skips the notification, without throwing, when publishing or hiding an object with no owner', function (): void {
    $geo = completenessGeography();
    $actor = User::factory()->create();

    $object = new Object_;
    $object->ulid = (string) Str::ulid();
    $object->object_type_id = $geo['type'];
    $object->territory_id = $geo['territory'];
    $object->country_id = $geo['country'];
    $object->status = 'draft';
    $object->save();

    app(ObjectLifecycleService::class)->publish($object, $actor);
    app(ObjectLifecycleService::class)->hide($object->fresh(), $actor);

    expect(Notification::query()->whereHas('type', fn ($q) => $q->where('key', 'object_status_changed'))->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| system_message — no single owning caller, must remain a usable type
|--------------------------------------------------------------------------
*/

it('keeps system_message registered and directly dispatchable even though no single caller owns it', function (): void {
    $type = NotificationType::query()->where('key', 'system_message')->firstOrFail();
    $recipient = User::factory()->create();

    $notification = app(NotificationDispatchService::class)->create($type, $recipient);

    expect($notification->notification_type_id)->toBe($type->id)
        ->and(NotificationDispatch::query()->where('notification_id', $notification->id)->exists())->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| DispatchRetryJob — the fourth re-run idempotency check
|--------------------------------------------------------------------------
*/

it('produces no additional retry attempt when DispatchRetryJob re-runs against a dispatch the first retry already resolved', function (): void {
    $typeId = DB::table('notification_types')->where('key', 'system_message')->value('id');
    $channelId = DB::table('notification_channels')->where('key', 'inbox')->value('id');
    $recipient = User::factory()->create();

    $notification = Notification::query()->create([
        'recipient_id' => $recipient->id,
        'notification_type_id' => $typeId,
        'title' => 'Test notification',
        'body' => 'Test body',
        'locale' => 'en',
    ]);

    $dispatch = NotificationDispatch::query()->create([
        'notification_id' => $notification->id,
        'notification_channel_id' => $channelId,
        'status' => 'failed',
        'retry_count' => 1,
        'failure_reason' => 'Simulated outage',
    ]);

    (new DispatchRetryJob)->handle(app(SettingsRepository::class));

    // The inbox adapter is a harmless no-op, so the sync-queue retry
    // resolves to `sent` on this first run.
    $afterFirstRun = $dispatch->fresh();
    expect($afterFirstRun->status)->toBe('sent')
        ->and($afterFirstRun->retry_count)->toBe(2);

    (new DispatchRetryJob)->handle(app(SettingsRepository::class));

    $afterSecondRun = $dispatch->fresh();
    expect($afterSecondRun->status)->toBe('sent')
        ->and($afterSecondRun->retry_count)->toBe(2);
});
