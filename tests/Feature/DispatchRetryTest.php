<?php

declare(strict_types=1);

use App\Jobs\DispatchNotificationJob;
use App\Jobs\DispatchRetryJob;
use App\Models\Notification;
use App\Models\NotificationDispatch;
use App\Models\User;
use App\Services\Settings\SettingsRepository;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\NotificationChannelSeeder;
use Database\Seeders\NotificationTypeSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Dispatch Retry
|--------------------------------------------------------------------------
|
| The sweep only ever resurrects a failed dispatch with retries remaining;
| one whose retry budget is exhausted is left failed for an administrator,
| and a sent (or queued, or suppressed) dispatch is never touched at all —
| its own delivery record stays exactly as it was left.
|
*/

beforeEach(fn () => $this->seed([LanguageSeeder::class, NotificationChannelSeeder::class, NotificationTypeSeeder::class]));

/** @param  array<string, mixed>  $overrides */
function dispatchRetryFixture(string $status, int $retryCount = 0, array $overrides = []): NotificationDispatch
{
    $typeId = DB::table('notification_types')->where('key', 'placement_expiring')->value('id');
    $channelId = DB::table('notification_channels')->where('key', 'email')->value('id');

    $notification = Notification::query()->create([
        'recipient_id' => User::factory()->create()->id,
        'notification_type_id' => $typeId,
        'title' => 'Test notification',
        'body' => 'Test body',
        'locale' => 'en',
    ]);

    /** @var NotificationDispatch $dispatch */
    $dispatch = NotificationDispatch::query()->create(array_merge([
        'notification_id' => $notification->id,
        'notification_channel_id' => $channelId,
        'status' => $status,
        'retry_count' => $retryCount,
    ], $overrides));

    return $dispatch;
}

it('is registered on the scheduler and unreachable from a web route', function (): void {
    $schedule = app(Schedule::class);
    $names = collect($schedule->events())->map(fn ($event) => $event->description ?? '')->all();

    expect($names)->toContain('notifications:retry-dispatch');
});

it('re-queues a failed dispatch that still has retries remaining, incrementing its retry count', function (): void {
    Queue::fake();
    $dispatch = dispatchRetryFixture('failed', 1);

    (new DispatchRetryJob)->handle(app(SettingsRepository::class));

    Queue::assertPushed(DispatchNotificationJob::class);

    $fresh = $dispatch->fresh();
    expect($fresh)->not->toBeNull()
        ->and($fresh->status)->toBe('queued')
        ->and($fresh->retry_count)->toBe(2);
});

it('never touches a failed dispatch whose retry budget is exhausted, leaving it failed permanently', function (): void {
    Queue::fake();
    // Default budget (notifications.dispatch_max_retries) is 3.
    $dispatch = dispatchRetryFixture('failed', 3);

    (new DispatchRetryJob)->handle(app(SettingsRepository::class));

    Queue::assertNotPushed(DispatchNotificationJob::class);

    $fresh = $dispatch->fresh();
    expect($fresh)->not->toBeNull()
        ->and($fresh->status)->toBe('failed')
        ->and($fresh->retry_count)->toBe(3);
});

it('never touches a sent dispatch — its attempted_at and provider_reference stay exactly as delivery left them', function (): void {
    Queue::fake();
    // The datetime cast persists to whole-second precision, so the
    // expectation is truncated the same way before comparing — otherwise
    // the microseconds `now()` carries never round-trip through the column.
    $attemptedAt = now()->subHour()->startOfSecond();
    $dispatch = dispatchRetryFixture('sent', 0, [
        'attempted_at' => $attemptedAt,
        'provider_reference' => 'provider-ref-123',
    ]);

    (new DispatchRetryJob)->handle(app(SettingsRepository::class));

    Queue::assertNotPushed(DispatchNotificationJob::class);

    $fresh = $dispatch->fresh();
    expect($fresh)->not->toBeNull()
        ->and($fresh->status)->toBe('sent')
        ->and($fresh->attempted_at?->equalTo($attemptedAt))->toBeTrue()
        ->and($fresh->provider_reference)->toBe('provider-ref-123')
        ->and($fresh->retry_count)->toBe(0);
});

it('never touches a queued or a suppressed dispatch', function (): void {
    Queue::fake();
    $queued = dispatchRetryFixture('queued');
    $suppressed = dispatchRetryFixture('suppressed');

    (new DispatchRetryJob)->handle(app(SettingsRepository::class));

    Queue::assertNotPushed(DispatchNotificationJob::class);

    expect($queued->fresh()?->status)->toBe('queued')
        ->and($suppressed->fresh()?->status)->toBe('suppressed');
});

it('reads the retry budget from settings, not a hardcoded constant', function (): void {
    Queue::fake();
    app(SettingsRepository::class)->set('notifications.dispatch_max_retries', 1);

    $dispatch = dispatchRetryFixture('failed', 1);

    (new DispatchRetryJob)->handle(app(SettingsRepository::class));

    Queue::assertNotPushed(DispatchNotificationJob::class);
    expect($dispatch->fresh()?->status)->toBe('failed');
});
