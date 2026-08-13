<?php

declare(strict_types=1);

namespace App\Services\Notifications\Channels;

use App\Models\Notification;
use App\Models\NotificationDispatch;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

/**
 * Sends the notification's already-rendered title/body as plain text to the
 * recipient's account email. No Blade layout yet — the public site does not
 * exist until a later phase, and a plain-text transactional message needs
 * none; a richer template is a presentation change behind this same
 * contract, not a pipeline change.
 */
final class EmailChannelAdapter implements NotificationChannelAdapter
{
    public function send(Notification $notification, NotificationDispatch $dispatch): void
    {
        $recipient = $notification->recipient;

        if (! $recipient instanceof User) {
            throw new RuntimeException("Recipient [{$notification->recipient_id}] no longer exists.");
        }

        Mail::raw($notification->body, function ($message) use ($recipient, $notification): void {
            $message->to($recipient->email)->subject($notification->title);
        });
    }
}
