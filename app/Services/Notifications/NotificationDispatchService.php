<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Jobs\DispatchNotificationJob;
use App\Models\Notification;
use App\Models\NotificationChannel;
use App\Models\NotificationDispatch;
use App\Models\NotificationTemplate;
use App\Models\NotificationType;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Creates notification records and drives their outbound delivery.
 *
 * `create()` renders and stores a notification's title/body exactly once, at
 * creation — a `NotificationTemplate` edited afterward must never change
 * what a recipient was already shown, so nothing here re-reads the template
 * at delivery time. `send()` is the channel-resolution pipeline: it turns
 * one stored notification into zero or more queued delivery attempts,
 * branching on the notification type's class (transactional vs optional)
 * and, for optional types, the recipient's own preference.
 */
final class NotificationDispatchService
{
    public function __construct(
        private readonly NotificationPreferenceService $preferences,
    ) {}

    /**
     * Creates the stored `Notification` row for $recipient and immediately
     * runs it through {@see self::send()}. The inbox entry (the row itself)
     * is therefore always available the moment this returns, regardless of
     * how outbound delivery goes.
     *
     * Title/body default to the `(type, locale, inbox channel)` template
     * variant — the same variant every channel's already-rendered message
     * reuses, per the notification model's own "rendered once" guarantee.
     * $title/$body let a caller that composes its own message (an
     * administrator broadcast, for one) supply it directly instead; either
     * way, whatever is stored here is exactly what the recipient sees, never
     * re-read from the template afterward. No per-user locale column exists
     * on `User` yet, so every recipient's locale falls back to the portal's
     * primary language; this is a known, deliberate gap, not an oversight.
     *
     * @param  ?Model  $related  the record the notification is about, if any — stored via the `related` morph relation
     * @param  ?User  $creator  the staff member who triggered this, if any — null means system-triggered
     * @param  ?string  $title  overrides the template subject when given
     * @param  ?string  $body  overrides the template body when given
     */
    public function create(NotificationType $type, User $recipient, ?Model $related = null, ?User $creator = null, ?string $title = null, ?string $body = null): Notification
    {
        $locale = $this->resolvePrimaryLocale();
        $template = $this->inboxTemplateFor($type, $locale);

        $notification = Notification::query()->create([
            'recipient_id' => $recipient->id,
            'notification_type_id' => $type->id,
            'related_type' => $related?->getMorphClass(),
            'related_id' => $related?->getKey(),
            'title' => $title ?? ($template->subject ?? Str::headline($type->key)),
            'body' => $body ?? ($template->body ?? ''),
            'locale' => $locale,
            'created_by' => $creator?->id,
        ]);

        $this->send($notification);

        return $notification;
    }

    /**
     * Resolves $notification's outbound channels and queues one delivery job
     * per channel actually attempted. Resolution is `type.default_channels`
     * narrowed to currently-active channels; a channel named by the type but
     * not (yet) active is silently excluded, never suppressed — suppression
     * is reserved for a channel the recipient could reach but chose not to.
     *
     * A transactional type dispatches on every resolved channel unconditionally.
     * An optional type the recipient has disabled records a `suppressed`
     * dispatch per resolved channel instead of queuing delivery — a recorded
     * fact, never a silent skip, so an administrator can answer "why didn't
     * this owner know" from the dispatch history alone.
     *
     * Idempotent per (notification, channel): a channel already resolved for
     * this notification (a dispatch row already exists) is left untouched —
     * calling this twice for the same notification never queues a second
     * attempt or overwrites a suppression.
     *
     * @throws RuntimeException when $notification's type or recipient no longer resolves
     */
    public function send(Notification $notification): void
    {
        $type = $notification->type;

        if (! $type instanceof NotificationType) {
            throw new RuntimeException("Notification [{$notification->id}] has no resolvable type.");
        }

        $recipient = $notification->recipient;

        if (! $recipient instanceof User) {
            throw new RuntimeException("Notification [{$notification->id}] has no resolvable recipient.");
        }

        $suppressed = ! $type->isTransactional() && ! $this->preferences->isEnabled($recipient, $type);

        foreach ($this->resolveActiveChannelIds($type) as $channelId) {
            $this->queueOrSuppress($notification, $channelId, $suppressed);
        }
    }

    /**
     * Marks $notification read, if it was not already. A no-op when it is
     * already read, so callers never need to check first.
     */
    public function markAsRead(Notification $notification): void
    {
        if ($notification->read_at === null) {
            $notification->read_at = now();
            $notification->save();
        }
    }

    /**
     * Reverts $notification to unread, if it was not already. A no-op when
     * it is already unread.
     */
    public function markAsUnread(Notification $notification): void
    {
        if ($notification->read_at !== null) {
            $notification->read_at = null;
            $notification->save();
        }
    }

    /**
     * True when a $type notification already exists for $related, created
     * within the last $windowDays days.
     *
     * The idempotency check a scheduled sweep uses before calling
     * {@see self::create()} again for the same target: a condition a sweep
     * re-evaluates on every run (an object still overdue, a status still
     * unconfirmed) would otherwise raise a fresh notification on every run
     * too, rather than once per window.
     */
    public function raisedWithin(Model $related, NotificationType $type, int $windowDays): bool
    {
        return Notification::query()
            ->where('related_type', $related->getMorphClass())
            ->where('related_id', $related->getKey())
            ->where('notification_type_id', $type->id)
            ->where('created_at', '>=', now()->subDays($windowDays))
            ->exists();
    }

    /**
     * Creates the `queued` (or `suppressed`) dispatch row for one channel and
     * queues its delivery job, unless a dispatch already exists for this
     * (notification, channel) pair — that existing row, whatever its status,
     * is left exactly as it is.
     */
    private function queueOrSuppress(Notification $notification, int $channelId, bool $suppressed): void
    {
        $alreadyResolved = NotificationDispatch::query()
            ->where('notification_id', $notification->id)
            ->where('notification_channel_id', $channelId)
            ->exists();

        if ($alreadyResolved) {
            return;
        }

        $dispatch = NotificationDispatch::query()->create([
            'notification_id' => $notification->id,
            'notification_channel_id' => $channelId,
            'status' => $suppressed ? 'suppressed' : 'queued',
            'attempted_at' => $suppressed ? now() : null,
        ]);

        if (! $suppressed) {
            DispatchNotificationJob::dispatch($dispatch->id);
        }
    }

    /**
     * @return list<int> ids of currently-active channels named in $type's default_channels
     */
    private function resolveActiveChannelIds(NotificationType $type): array
    {
        $ids = NotificationChannel::query()
            ->where('is_active', true)
            ->whereIn('key', $type->default_channels)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        return array_values($ids);
    }

    private function inboxTemplateFor(NotificationType $type, string $locale): ?NotificationTemplate
    {
        return NotificationTemplate::query()
            ->where('notification_type_id', $type->id)
            ->where('locale', $locale)
            ->whereHas('channel', fn ($query) => $query->where('key', 'inbox'))
            ->first();
    }

    private function resolvePrimaryLocale(): string
    {
        return (string) (DB::table('languages')->where('is_primary', true)->value('code') ?? 'en');
    }
}
