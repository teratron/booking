<?php

declare(strict_types=1);

use App\Jobs\DispatchNotificationJob;
use App\Models\Notification;
use App\Models\NotificationChannel;
use App\Models\NotificationDispatch;
use App\Models\NotificationTemplate;
use App\Models\NotificationType;
use App\Models\User;
use App\Services\Notifications\Channels\EmailChannelAdapter;
use App\Services\Notifications\Channels\NotificationChannelAdapter;
use App\Services\Notifications\NotificationChannelAdapterRegistry;
use App\Services\Notifications\NotificationDispatchService;
use App\Services\Notifications\NotificationPreferenceService;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\NotificationChannelSeeder;
use Database\Seeders\NotificationTemplateSeeder;
use Database\Seeders\NotificationTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// Seeded exactly as a fresh install seeds it — the same reasoning
// NotificationModelTest.php already documents for this beforeEach.
beforeEach(fn () => $this->seed([LanguageSeeder::class, NotificationChannelSeeder::class, NotificationTypeSeeder::class, NotificationTemplateSeeder::class]));

/*
|--------------------------------------------------------------------------
| Dispatch Pipeline — Channel Resolution, Suppression, Idempotency
|--------------------------------------------------------------------------
|
| QUEUE_CONNECTION=sync in the test environment, so DispatchNotificationJob
| runs inline the moment it is queued — no fake queue is needed to observe
| the resulting NotificationDispatch rows. The only channel ever stubbed is
| 'email' (via a container binding for EmailChannelAdapter); 'inbox' is left
| to run its real, harmless no-op adapter.
|
*/

it('dispatches a transactional notification on every resolved channel regardless of recipient preference, and keeps the inbox entry even with every channel disabled', function (): void {
    $type = NotificationType::query()->where('key', 'system_message')->firstOrFail();
    $recipient = User::factory()->create();
    $service = app(NotificationDispatchService::class);

    // A disabled preference on a transactional type must never be
    // consulted — this is what makes the assertions below prove
    // "regardless of preference" rather than merely "preference happened
    // to be enabled".
    app(NotificationPreferenceService::class)->setEnabled($recipient, $type, false);

    $notification = $service->create($type, $recipient);

    $dispatches = NotificationDispatch::query()->where('notification_id', $notification->id)->get();

    expect($dispatches)->toHaveCount(2)
        ->and($dispatches->pluck('status')->unique()->all())->toBe(['sent']);

    // Now disable every channel the type would ever resolve to, and create
    // a second notification — the record itself (the inbox entry) must
    // still exist, with zero dispatch rows since nothing resolved.
    NotificationChannel::query()->whereIn('key', ['email', 'inbox'])->update(['is_active' => false]);

    $secondNotification = $service->create($type, $recipient);

    expect(Notification::query()->find($secondNotification->id))->not->toBeNull()
        ->and(NotificationDispatch::query()->where('notification_id', $secondNotification->id)->count())->toBe(0);
});

it('records a suppressed dispatch, not a silent skip, when the recipient has disabled an optional type', function (): void {
    $type = NotificationType::query()->where('key', 'information_out_of_date')->firstOrFail();
    $recipient = User::factory()->create();
    $service = app(NotificationDispatchService::class);

    expect($type->isTransactional())->toBeFalse();

    app(NotificationPreferenceService::class)->setEnabled($recipient, $type, false);

    $notification = $service->create($type, $recipient);

    $dispatch = NotificationDispatch::query()->where('notification_id', $notification->id)->firstOrFail();

    expect($dispatch->status)->toBe('suppressed')
        ->and($dispatch->attempted_at)->not->toBeNull();
});

it('leaves an optional type undisturbed and delivers normally when no preference row exists yet', function (): void {
    $type = NotificationType::query()->where('key', 'information_out_of_date')->firstOrFail();
    $recipient = User::factory()->create();
    $service = app(NotificationDispatchService::class);

    // No NotificationPreference row at all — absence must mean enabled.
    $notification = $service->create($type, $recipient);

    $dispatch = NotificationDispatch::query()->where('notification_id', $notification->id)->firstOrFail();

    expect($dispatch->status)->toBe('sent');
});

it('never sends a second message when the delivery job runs twice for an already-sent dispatch', function (): void {
    $type = NotificationType::query()->where('key', 'system_message')->firstOrFail();
    $recipient = User::factory()->create();
    $service = app(NotificationDispatchService::class);

    $countingAdapter = new class implements NotificationChannelAdapter
    {
        public int $calls = 0;

        public function send(Notification $notification, NotificationDispatch $dispatch): void
        {
            $this->calls++;
        }
    };
    app()->instance(EmailChannelAdapter::class, $countingAdapter);

    $notification = $service->create($type, $recipient);

    $emailChannelId = DB::table('notification_channels')->where('key', 'email')->value('id');
    $emailDispatch = NotificationDispatch::query()
        ->where('notification_id', $notification->id)
        ->where('notification_channel_id', $emailChannelId)
        ->firstOrFail();

    expect($emailDispatch->status)->toBe('sent')
        ->and($countingAdapter->calls)->toBe(1);

    // Re-running the same job for the same dispatch row must be a no-op.
    (new DispatchNotificationJob($emailDispatch->id))->handle(app(NotificationChannelAdapterRegistry::class));

    expect($countingAdapter->calls)->toBe(1)
        ->and($emailDispatch->fresh()->status)->toBe('sent');
});

it('retries a failed dispatch with backoff and marks it failed only once retries are exhausted', function (): void {
    $type = NotificationType::query()->where('key', 'system_message')->firstOrFail();
    $recipient = User::factory()->create();
    $emailChannelId = DB::table('notification_channels')->where('key', 'email')->value('id');

    $notification = Notification::query()->create([
        'recipient_id' => $recipient->id,
        'notification_type_id' => $type->id,
        'title' => 'System notification',
        'body' => 'Body',
        'locale' => 'en',
    ]);

    $dispatch = NotificationDispatch::query()->create([
        'notification_id' => $notification->id,
        'notification_channel_id' => $emailChannelId,
        'status' => 'queued',
    ]);

    $failingAdapter = new class implements NotificationChannelAdapter
    {
        public int $calls = 0;

        public function send(Notification $notification, NotificationDispatch $dispatch): void
        {
            $this->calls++;

            throw new RuntimeException('Simulated SMTP outage');
        }
    };
    app()->instance(EmailChannelAdapter::class, $failingAdapter);

    $job = new DispatchNotificationJob($dispatch->id);
    $registry = app(NotificationChannelAdapterRegistry::class);

    // Configured to actually retry, not fail on the first attempt.
    expect($job->tries)->toBeGreaterThan(1)
        ->and($job->backoff())->not->toBeEmpty();

    expect(fn () => $job->handle($registry))->toThrow(RuntimeException::class, 'Simulated SMTP outage');

    // One failed attempt alone must not finalize the dispatch — it stays
    // queued so the queue's own retry/backoff can try again.
    expect($dispatch->fresh()->status)->toBe('queued')
        ->and($dispatch->fresh()->failure_reason)->toBe('Simulated SMTP outage')
        ->and($failingAdapter->calls)->toBe(1);

    // The queue calls failed() once every configured attempt is exhausted —
    // simulated here directly, exactly as Laravel's worker would invoke it.
    $job->failed(new RuntimeException('Simulated SMTP outage'));

    expect($dispatch->fresh()->status)->toBe('failed')
        ->and($dispatch->fresh()->failure_reason)->toBe('Simulated SMTP outage');
});

it('renders the template into title and body at creation time and never re-reads it afterward', function (): void {
    $type = NotificationType::query()->where('key', 'placement_expiring')->firstOrFail();
    $recipient = User::factory()->create();
    $service = app(NotificationDispatchService::class);

    $notification = $service->create($type, $recipient);

    $originalTitle = $notification->title;
    $originalBody = $notification->body;

    expect($originalBody)->not->toBe('');

    $inboxTemplate = NotificationTemplate::query()
        ->where('notification_type_id', $type->id)
        ->where('locale', 'en')
        ->whereHas('channel', fn ($query) => $query->where('key', 'inbox'))
        ->firstOrFail();

    $inboxTemplate->update(['body' => 'Wording that did not exist when the notification above was created.']);

    expect($notification->fresh()->title)->toBe($originalTitle)
        ->and($notification->fresh()->body)->toBe($originalBody)
        ->and($notification->fresh()->body)->not->toBe($inboxTemplate->fresh()->body);
});

/*
|--------------------------------------------------------------------------
| Preferences
|--------------------------------------------------------------------------
*/

it('defaults an unconfigured preference to enabled and persists an explicit choice', function (): void {
    $type = NotificationType::query()->where('key', 'confirm_availability_status')->firstOrFail();
    $recipient = User::factory()->create();
    $service = app(NotificationPreferenceService::class);

    expect($service->isEnabled($recipient, $type))->toBeTrue();

    $service->setEnabled($recipient, $type, false);

    expect($service->isEnabled($recipient, $type))->toBeFalse();

    $service->setEnabled($recipient, $type, true);

    expect($service->isEnabled($recipient, $type))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Read / Unread
|--------------------------------------------------------------------------
*/

it('marks a notification read and back to unread idempotently', function (): void {
    $type = NotificationType::query()->where('key', 'system_message')->firstOrFail();
    $recipient = User::factory()->create();
    $service = app(NotificationDispatchService::class);

    $notification = Notification::query()->create([
        'recipient_id' => $recipient->id,
        'notification_type_id' => $type->id,
        'title' => 'T',
        'body' => 'B',
        'locale' => 'en',
    ]);

    expect($notification->read_at)->toBeNull();

    $service->markAsRead($notification);
    $service->markAsRead($notification); // idempotent — a second call must not error or move the timestamp meaningfully

    expect($notification->fresh()->read_at)->not->toBeNull();

    $service->markAsUnread($notification);

    expect($notification->fresh()->read_at)->toBeNull();
});
