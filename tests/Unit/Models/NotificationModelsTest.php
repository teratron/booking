<?php

declare(strict_types=1);

use App\Models\Notification;
use App\Models\NotificationChannel;
use App\Models\NotificationPreference;
use App\Models\NotificationTemplate;
use App\Models\NotificationType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Notification Family Models — custom casts, relations, and business rules
|--------------------------------------------------------------------------
|
| tests/Feature/NotificationModelTest.php already covers Notification's own
| record-independence and dispatch-status behavior at a higher level; this
| file covers what each of the five models in this family adds beyond
| inherited Eloquent boilerplate: NotificationChannel's is_active cast and
| Translatable name proxy, NotificationPreference's is_enabled cast,
| NotificationType's default_channels array cast plus isTransactional(),
| and each model's own non-default-FK relations. NotificationTemplate
| carries no cast or business-rule method of its own — its type()/channel()
| relations are covered here since both use an explicit, non-default
| foreign key column.
|
*/

function notificationModelsLanguage(): int
{
    return DB::table('languages')->insertGetId([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function notificationModelsChannel(string $key = 'email'): NotificationChannel
{
    notificationModelsLanguage();

    return NotificationChannel::query()->create(['key' => $key, 'is_active' => true]);
}

/** @param  array<int, string>  $defaultChannels */
function notificationModelsType(string $class = 'transactional', array $defaultChannels = ['email']): NotificationType
{
    return NotificationType::query()->create([
        'key' => 'placement_expiring_'.$class,
        'class' => $class,
        'default_channels' => $defaultChannels,
        'is_active' => true,
    ]);
}

// --- NotificationChannel -------------------------------------------------

it('casts NotificationChannel.is_active to boolean and resolves its translated name', function (): void {
    $channel = notificationModelsChannel('inbox');

    DB::table('notification_channel_translations')->insert([
        'notification_channel_id' => $channel->id, 'locale' => 'en', 'name' => 'Inbox',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $fresh = NotificationChannel::query()->findOrFail($channel->id);

    expect($fresh->is_active)->toBeTrue()
        ->and($fresh->is_active)->toBeBool()
        ->and($fresh->name)->toBe('Inbox');
});

it('resolves NotificationChannel.templates and NotificationChannel.dispatches to the correct rows', function (): void {
    $channel = notificationModelsChannel();
    $type = notificationModelsType();

    $templateId = DB::table('notification_templates')->insertGetId([
        'notification_type_id' => $type->id, 'locale' => 'en', 'notification_channel_id' => $channel->id,
        'subject' => 'Subject', 'body' => 'Body', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $recipient = User::factory()->create();
    $notification = Notification::query()->create([
        'recipient_id' => $recipient->id, 'notification_type_id' => $type->id,
        'title' => 'T', 'body' => 'B', 'locale' => 'en',
    ]);
    $dispatchId = DB::table('notification_dispatches')->insertGetId([
        'notification_id' => $notification->id, 'notification_channel_id' => $channel->id,
        'status' => 'sent', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $fresh = NotificationChannel::query()->findOrFail($channel->id);

    expect($fresh->templates->pluck('id')->all())->toBe([$templateId])
        ->and($fresh->dispatches->pluck('id')->all())->toBe([$dispatchId]);
});

// --- NotificationPreference ------------------------------------------------

it('casts NotificationPreference.is_enabled to boolean and resolves its user and type', function (): void {
    $user = User::factory()->create();
    $type = notificationModelsType('optional');

    $preference = NotificationPreference::query()->create([
        'user_id' => $user->id, 'notification_type_id' => $type->id, 'is_enabled' => false,
    ]);

    $fresh = NotificationPreference::query()->findOrFail($preference->id);

    expect($fresh->is_enabled)->toBeFalse()
        ->and($fresh->is_enabled)->toBeBool()
        ->and($fresh->user->id)->toBe($user->id)
        ->and($fresh->type->id)->toBe($type->id);
});

// --- NotificationTemplate ---------------------------------------------------

it('resolves NotificationTemplate.type and NotificationTemplate.channel through their explicit foreign keys', function (): void {
    $channel = notificationModelsChannel();
    $type = notificationModelsType();

    $templateId = DB::table('notification_templates')->insertGetId([
        'notification_type_id' => $type->id, 'locale' => 'en', 'notification_channel_id' => $channel->id,
        'subject' => null, 'body' => 'Body', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $fresh = NotificationTemplate::query()->findOrFail($templateId);

    expect($fresh->type->id)->toBe($type->id)
        ->and($fresh->channel->id)->toBe($channel->id)
        ->and($fresh->subject)->toBeNull();
});

// --- NotificationType --------------------------------------------------------

it('casts NotificationType.default_channels to an array and is_active to boolean', function (): void {
    $type = notificationModelsType('transactional', ['email', 'inbox']);

    $fresh = NotificationType::query()->findOrFail($type->id);

    expect($fresh->default_channels)->toBe(['email', 'inbox'])
        ->and($fresh->is_active)->toBeTrue()
        ->and($fresh->is_active)->toBeBool();
});

it('reports isTransactional() correctly for each notification class', function (): void {
    $transactional = notificationModelsType('transactional');
    $optional = NotificationType::query()->create([
        'key' => 'optional_digest', 'class' => 'optional', 'default_channels' => ['email'], 'is_active' => true,
    ]);

    expect($transactional->isTransactional())->toBeTrue()
        ->and($optional->isTransactional())->toBeFalse();
});

it('resolves NotificationType.templates, NotificationType.notifications, and NotificationType.preferences', function (): void {
    $channel = notificationModelsChannel();
    $type = notificationModelsType();

    $templateId = DB::table('notification_templates')->insertGetId([
        'notification_type_id' => $type->id, 'locale' => 'en', 'notification_channel_id' => $channel->id,
        'subject' => null, 'body' => 'Body', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $recipient = User::factory()->create();
    $notification = Notification::query()->create([
        'recipient_id' => $recipient->id, 'notification_type_id' => $type->id,
        'title' => 'T', 'body' => 'B', 'locale' => 'en',
    ]);

    $preference = NotificationPreference::query()->create([
        'user_id' => $recipient->id, 'notification_type_id' => $type->id, 'is_enabled' => true,
    ]);

    $fresh = NotificationType::query()->findOrFail($type->id);

    expect($fresh->templates->pluck('id')->all())->toBe([$templateId])
        ->and($fresh->notifications->pluck('id')->all())->toBe([$notification->id])
        ->and($fresh->preferences->pluck('id')->all())->toBe([$preference->id]);
});

// --- Notification ----------------------------------------------------------
// tests/Feature/NotificationModelTest.php already covers record independence
// and per-dispatch status; this covers only the cast and relation shapes
// that file does not.

it('casts Notification.read_at to a Carbon instance and leaves it null until read', function (): void {
    notificationModelsLanguage();
    $recipient = User::factory()->create();
    $type = notificationModelsType();

    $notification = Notification::query()->create([
        'recipient_id' => $recipient->id, 'notification_type_id' => $type->id,
        'title' => 'T', 'body' => 'B', 'locale' => 'en',
    ]);

    expect(Notification::query()->findOrFail($notification->id)->read_at)->toBeNull();

    $notification->forceFill(['read_at' => now()])->save();

    expect(Notification::query()->findOrFail($notification->id)->read_at)->toBeInstanceOf(Carbon::class);
});

it('resolves Notification.recipient, Notification.creator, and Notification.related polymorphically', function (): void {
    notificationModelsLanguage();
    $recipient = User::factory()->create();
    $creator = User::factory()->create();
    $type = notificationModelsType();
    $relatedType = notificationModelsType('optional');

    $notification = Notification::query()->create([
        'recipient_id' => $recipient->id, 'notification_type_id' => $type->id,
        'related_type' => NotificationType::class, 'related_id' => $relatedType->id,
        'title' => 'T', 'body' => 'B', 'locale' => 'en', 'created_by' => $creator->id,
    ]);

    $fresh = Notification::query()->findOrFail($notification->id);

    expect($fresh->recipient->id)->toBe($recipient->id)
        ->and($fresh->creator->id)->toBe($creator->id)
        ->and($fresh->related)->not->toBeNull()
        ->and($fresh->related->id)->toBe($relatedType->id);
});
