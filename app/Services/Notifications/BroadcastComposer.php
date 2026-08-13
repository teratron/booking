<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Exceptions\BroadcastRateLimitedException;
use App\Models\NotificationType;
use App\Models\Object_;
use App\Models\User;
use App\Services\Audit\AuditJournal;
use App\Services\Settings\SettingsRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;
use OwenIt\Auditing\Models\Audit;
use RuntimeException;

/**
 * Resolves an administrator's targeting choice — a country, a resort
 * (territory node), or a placement package — into the distinct set of owner
 * accounts it reaches, and sends each of them one `administration_message`
 * notification through {@see NotificationDispatchService}. This is the only
 * delivery path a broadcast uses: every recipient gets an ordinary
 * notification, subject to the same channel resolution, suppression, and
 * idempotency guarantees as any other notification in the system.
 *
 * An owner with several objects that all match the same targeting criterion
 * is still messaged exactly once — recipients are resolved as a distinct set
 * of accounts before any notification is created, never one per matching
 * object.
 */
final class BroadcastComposer
{
    public const string TARGET_COUNTRY = 'country';

    public const string TARGET_RESORT = 'resort';

    public const string TARGET_PACKAGE = 'package';

    /**
     * The action-journal event a sent broadcast is recorded under — both the
     * audit trail entry and the rate-limit accounting read this same event,
     * rather than keeping a second, purpose-built counter table.
     */
    private const string BROADCAST_EVENT = 'administrator_broadcast_sent';

    public function __construct(
        private readonly NotificationDispatchService $dispatcher,
        private readonly AuditJournal $journal,
        private readonly SettingsRepository $settings,
    ) {}

    /**
     * The distinct owner accounts $targetType/$targetId currently resolves
     * to — the exact set {@see self::send()} would message, exposed
     * separately so a compose screen can show an accurate recipient count
     * before anything is sent.
     *
     * @return Collection<int, User>
     *
     * @throws InvalidArgumentException when $targetType is not one of the three recognised kinds
     */
    public function recipients(string $targetType, int $targetId): Collection
    {
        $ownerIds = $this->targetQuery($targetType, $targetId)
            ->distinct()
            ->pluck('owner_id');

        return User::query()->whereIn('id', $ownerIds)->get();
    }

    /**
     * How many distinct owners $targetType/$targetId currently resolves to.
     * Returns 0 for an unresolved target instead of throwing, since a
     * compose screen calls this continuously as the administrator is still
     * choosing what to target.
     */
    public function recipientCount(?string $targetType, ?int $targetId): int
    {
        if ($targetType === null || $targetId === null) {
            return 0;
        }

        return $this->recipients($targetType, $targetId)->count();
    }

    /**
     * How many broadcasts remain available for the rest of today, under
     * `notifications.broadcast_rate_limit`. Never negative.
     */
    public function remainingQuota(): int
    {
        $limit = (int) $this->settings->get('notifications.broadcast_rate_limit');

        $sentToday = Audit::query()
            ->where('event', self::BROADCAST_EVENT)
            ->where('created_at', '>=', now()->startOfDay())
            ->count();

        return max(0, $limit - $sentToday);
    }

    public function isRateLimited(): bool
    {
        return $this->remainingQuota() <= 0;
    }

    /**
     * Resolves $targetType/$targetId to its distinct owners and sends each
     * one an `administration_message` notification carrying $title/$body,
     * through the shared dispatch pipeline.
     *
     * Checks the daily quota first and touches no recipient at all if it is
     * already spent — a refusal here is always a refusal of the whole
     * broadcast, never a partial send.
     *
     * @return int the number of recipients actually messaged
     *
     * @throws BroadcastRateLimitedException when today's `notifications.broadcast_rate_limit` is already spent
     * @throws RuntimeException when the `administration_message` notification type is not registered
     * @throws InvalidArgumentException when $targetType is not one of the three recognised kinds
     */
    public function send(string $targetType, int $targetId, string $title, string $body, User $actor): int
    {
        if ($this->isRateLimited()) {
            throw BroadcastRateLimitedException::forLimit((int) $this->settings->get('notifications.broadcast_rate_limit'));
        }

        $type = NotificationType::query()->where('key', 'administration_message')->first();

        if (! $type instanceof NotificationType) {
            throw new RuntimeException('Notification type [administration_message] is not registered.');
        }

        $recipients = $this->recipients($targetType, $targetId);

        foreach ($recipients as $recipient) {
            $this->dispatcher->create($type, $recipient, null, $actor, $title, $body);
        }

        $this->journal->record(self::BROADCAST_EVENT, $actor, [], [
            'target_type' => $targetType,
            'target_id' => $targetId,
            'recipient_count' => $recipients->count(),
            'title' => $title,
        ], $actor, ['notification', 'broadcast']);

        return $recipients->count();
    }

    /**
     * @return Builder<Object_>
     */
    private function targetQuery(string $targetType, int $targetId): Builder
    {
        // Bulk administrative messaging looks past moderation status, the
        // same reasoning ObjectBulkActionService already applies to every
        // other back-office bulk operation: an owner whose object sits in
        // review still owns that object, and a broadcast concerns them
        // regardless of what a visitor can currently see on the public site.
        $query = Object_::query()->withUnmoderated();

        $scoped = match ($targetType) {
            self::TARGET_COUNTRY => $query->where('country_id', $targetId),
            self::TARGET_RESORT => $query->where('territory_id', $targetId),
            self::TARGET_PACKAGE => $query->whereHas(
                'placement',
                fn (Builder $placementQuery): Builder => $placementQuery->where('placement_package_id', $targetId),
            ),
            default => throw new InvalidArgumentException("Unknown broadcast target type [{$targetType}]."),
        };

        return $scoped->whereNotNull('owner_id');
    }
}
