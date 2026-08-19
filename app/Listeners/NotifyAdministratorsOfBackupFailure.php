<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\NotificationType;
use App\Models\User;
use App\Services\Notifications\NotificationDispatchService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Backup\Events\BackupHasFailed;
use Spatie\Backup\Events\UnhealthyBackupWasFound;

/**
 * Routes a backup failure into the platform's own notification model — the
 * `system_message` type it already keeps registered for exactly this "no
 * single owning caller" shape of alert (see `NotificationTypeSeeder`) —
 * rather than any of Spatie's own notification channels, which
 * `config/backup.php` deliberately leaves unconfigured.
 *
 * Both of Spatie's own failure signals are covered: `BackupHasFailed` fires
 * from a `backup:run` that threw, `UnhealthyBackupWasFound` fires from
 * `backup:monitor` finding the destination unreachable, stale, oversized, or
 * (via `BackupArchiveIntegrityHealthCheck`) holding a corrupted artefact.
 * Laravel discovers both handlers automatically from their typed parameter —
 * no explicit registration, the same convention this project's sign-in
 * journal listeners already use.
 *
 * Recipients are every account holding the `settings_management`
 * permission — the same gate the backup administration screen itself
 * checks, so whoever can see the screen this alert points at is exactly
 * who is told about it. The message body is deliberately plain
 * English regardless of the recipient's own locale: this is an operational
 * diagnostic in the same family as a log line or an exception message, not
 * interface copy, and the specific failure detail would otherwise have
 * nowhere accurate to live in a pre-seeded translation catalog.
 */
final class NotifyAdministratorsOfBackupFailure
{
    public function __construct(private readonly NotificationDispatchService $dispatcher) {}

    public function handleBackupFailed(BackupHasFailed $event): void
    {
        $this->notify('Off-server backup failed', $event->exception->getMessage());
    }

    public function handleUnhealthyBackup(UnhealthyBackupWasFound $event): void
    {
        $reasons = $event->failureMessages
            ->map(fn (array $failure): string => "[{$failure['check']}] {$failure['message']}")
            ->implode(' ');

        $this->notify(
            "Backup destination [{$event->diskName}] is unhealthy",
            $reasons !== '' ? $reasons : 'One or more health checks failed.',
        );
    }

    private function notify(string $title, string $body): void
    {
        $type = NotificationType::query()->where('key', 'system_message')->first();

        if (! $type instanceof NotificationType) {
            return;
        }

        foreach ($this->administrators() as $administrator) {
            $this->dispatcher->create($type, $administrator, null, null, $title, $body);
        }
    }

    /**
     * Users holding `settings_management` via any role — a plain
     * relationship filter that degrades to an empty collection rather than
     * throwing when the permission has not been seeded in a given
     * environment or fixture, unlike Spatie's own `permission()` model
     * scope.
     *
     * @return Collection<int, User>
     */
    private function administrators(): Collection
    {
        return User::query()
            ->whereHas('roles', fn (Builder $roleQuery): Builder => $roleQuery->whereHas(
                'permissions',
                fn (Builder $permissionQuery): Builder => $permissionQuery->where('name', 'settings_management'),
            ))
            ->get();
    }
}
