<?php

declare(strict_types=1);

namespace App\Services\Backup;

use App\Models\NotificationType;
use App\Models\User;
use App\Services\Audit\AuditJournal;
use App\Services\Notifications\NotificationDispatchService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Records what happened to one administrator-triggered database restore,
 * independently of whether it succeeded — the counterpart to
 * {@see DatabaseRestoreService}'s purely mechanical work, kept as its own
 * service specifically so both outcomes can be exercised directly in a test
 * without ever invoking `psql` (which {@see DatabaseRestoreService::restore()}
 * is `final` and never fakes).
 *
 * Every entry names the acting administrator and the restored artefact's
 * own real timestamp, and every outcome — success or failure — also
 * notifies whoever holds `backup_restore`, the same administrator-only
 * audience the restore screen itself is gated on.
 */
final class BackupRestoreOutcomeRecorder
{
    public function __construct(
        private readonly DatabaseRestoreService $restores,
        private readonly AuditJournal $journal,
    ) {}

    public function recordSuccess(string $backupPath, User $actor): void
    {
        $timestamp = $this->timestampLabel($backupPath);

        $this->journal->record('backup_restored', $actor, [], [
            'backup_path' => $backupPath,
            'backup_timestamp' => $timestamp,
        ], $actor, ['backup', 'restore']);

        $this->notifyAdministrators(
            'Database restore completed',
            "The database was restored from the backup taken at {$timestamp}, triggered by {$actor->name}.",
        );
    }

    public function recordFailure(string $backupPath, User $actor, string $error): void
    {
        $timestamp = $this->timestampLabel($backupPath);

        $this->journal->record('backup_restore_failed', $actor, [], [
            'backup_path' => $backupPath,
            'backup_timestamp' => $timestamp,
            'error' => $error,
        ], $actor, ['backup', 'restore']);

        $this->notifyAdministrators(
            'Database restore failed',
            "Restoring the backup taken at {$timestamp} failed: {$error}",
        );
    }

    private function timestampLabel(string $backupPath): string
    {
        return $this->restores->find($backupPath)?->date()->toDateTimeString() ?? $backupPath;
    }

    /**
     * A plain relationship filter that degrades to an empty collection
     * rather than throwing when the permission has not been seeded in a
     * given environment or fixture — the same choice the scheduled-backup
     * failure listener already made for the equivalent backup-failure
     * audience.
     */
    private function notifyAdministrators(string $title, string $body): void
    {
        $type = NotificationType::query()->where('key', 'system_message')->first();

        if (! $type instanceof NotificationType) {
            return;
        }

        $dispatcher = app(NotificationDispatchService::class);

        foreach ($this->administrators() as $administrator) {
            $dispatcher->create($type, $administrator, null, null, $title, $body);
        }
    }

    /** @return Collection<int, User> */
    private function administrators(): Collection
    {
        return User::query()
            ->whereHas('roles', fn (Builder $roleQuery): Builder => $roleQuery->whereHas(
                'permissions',
                fn (Builder $permissionQuery): Builder => $permissionQuery->where('name', 'backup_restore'),
            ))
            ->get();
    }
}
