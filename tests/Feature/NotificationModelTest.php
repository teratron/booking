<?php

declare(strict_types=1);

use App\Models\Notification;
use App\Models\NotificationDispatch;
use App\Models\NotificationTemplate;
use App\Models\NotificationType;
use App\Models\User;
use App\Services\Notifications\Channels\NotificationChannelAdapter;
use App\Services\Notifications\NotificationChannelAdapterRegistry;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\NotificationChannelSeeder;
use Database\Seeders\NotificationTemplateSeeder;
use Database\Seeders\NotificationTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// The registry rows this file exercises are exactly what the real seeders
// produce for a fresh install — seeding them here (rather than hand-rolling
// the same ten types as raw inserts) is what lets "all ten types are
// seeded correctly" mean the same thing in this test as it does in
// production.
beforeEach(fn () => $this->seed([LanguageSeeder::class, NotificationChannelSeeder::class, NotificationTypeSeeder::class, NotificationTemplateSeeder::class]));

/*
|--------------------------------------------------------------------------
| Notification & Dispatch Model
|--------------------------------------------------------------------------
|
| A Notification is a record, not a send — proven here by reading one back
| with zero successful dispatches. The rendered-at-creation guarantee is
| checked directly: editing a template after a notification exists must not
| change what that notification already says.
|
*/

it('reads back as a record independently of any successful delivery', function (): void {
    $type = NotificationType::query()->where('key', 'system_message')->firstOrFail();
    $recipient = User::factory()->create();

    $notification = Notification::query()->create([
        'recipient_id' => $recipient->id,
        'notification_type_id' => $type->id,
        'title' => 'Test',
        'body' => 'Test body',
        'locale' => 'en',
    ]);

    // No dispatch row at all yet — the notification is already a complete,
    // readable record.
    expect(Notification::query()->find($notification->id))->not->toBeNull()
        ->and($notification->read_at)->toBeNull();

    NotificationDispatch::query()->create([
        'notification_id' => $notification->id,
        'notification_channel_id' => DB::table('notification_channels')->where('key', 'email')->value('id'),
        'status' => 'failed',
        'failure_reason' => 'SMTP timeout',
    ]);

    expect(Notification::query()->find($notification->id))->not->toBeNull();
});

it('records each dispatch\'s own status independently of the others', function (): void {
    $type = NotificationType::query()->where('key', 'system_message')->firstOrFail();
    $recipient = User::factory()->create();
    $notification = Notification::query()->create([
        'recipient_id' => $recipient->id, 'notification_type_id' => $type->id,
        'title' => 'T', 'body' => 'B', 'locale' => 'en',
    ]);

    $emailChannelId = DB::table('notification_channels')->where('key', 'email')->value('id');
    $inboxChannelId = DB::table('notification_channels')->where('key', 'inbox')->value('id');

    NotificationDispatch::query()->create(['notification_id' => $notification->id, 'notification_channel_id' => $emailChannelId, 'status' => 'sent']);
    NotificationDispatch::query()->create(['notification_id' => $notification->id, 'notification_channel_id' => $inboxChannelId, 'status' => 'suppressed']);

    $statuses = $notification->dispatches()->pluck('status', 'notification_channel_id');

    expect($statuses[$emailChannelId])->toBe('sent')
        ->and($statuses[$inboxChannelId])->toBe('suppressed');
});

it('never retroactively changes an already-created notification when its template is edited afterward', function (): void {
    $type = NotificationType::query()->where('key', 'placement_expiring')->firstOrFail();
    $template = NotificationTemplate::query()
        ->where('notification_type_id', $type->id)
        ->where('locale', 'en')
        ->whereHas('channel', fn ($q) => $q->where('key', 'inbox'))
        ->firstOrFail();

    $recipient = User::factory()->create();
    $notification = Notification::query()->create([
        'recipient_id' => $recipient->id, 'notification_type_id' => $type->id,
        'title' => 'Placement expiring', 'body' => $template->body, 'locale' => 'en',
    ]);

    $originalBody = $notification->body;

    $template->update(['body' => 'This wording did not exist when the notification above was created.']);

    expect($notification->fresh()->body)->toBe($originalBody)
        ->and($notification->fresh()->body)->not->toBe($template->fresh()->body);
});

it('seeds all ten specification-named types with their class and default channels', function (): void {
    $expected = [
        'placement_expiring' => ['transactional', ['email', 'inbox']],
        'package_expired' => ['transactional', ['email', 'inbox']],
        'moderation_approved' => ['transactional', ['email', 'inbox']],
        'moderation_rejected' => ['transactional', ['email', 'inbox']],
        'revision_requested' => ['transactional', ['email', 'inbox']],
        'information_out_of_date' => ['optional', ['inbox']],
        'confirm_availability_status' => ['optional', ['inbox']],
        'administration_message' => ['optional', ['inbox']],
        'object_status_changed' => ['transactional', ['email', 'inbox']],
        'system_message' => ['transactional', ['email', 'inbox']],
    ];

    $types = NotificationType::query()->get()->keyBy('key');

    expect($types)->toHaveCount(10);

    foreach ($expected as $key => [$class, $channels]) {
        $type = $types[$key] ?? null;

        expect($type)->not->toBeNull("Missing notification type [{$key}]")
            ->and($type->class)->toBe($class)
            ->and($type->default_channels)->toBe($channels)
            ->and($type->is_active)->toBeTrue();
    }
});

it('provides a registered adapter for every seeded, active channel', function (): void {
    $registry = app(NotificationChannelAdapterRegistry::class);

    foreach (['inbox', 'email'] as $key) {
        expect($registry->forKey($key))->toBeInstanceOf(NotificationChannelAdapter::class);
    }
});
