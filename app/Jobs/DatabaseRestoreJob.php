<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\NotificationType;
use App\Models\User;
use App\Services\Audit\AuditJournal;
use App\Services\Backup\DatabaseRestoreService;
use App\Services\Notifications\NotificationDispatchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * The portal's single most destructive queued operation: replaces the
 * running application database with a previously produced backup artefact.
 * Dispatched only from the staff panel's own restore screen, behind its
 * re-authentication gate, never reachable from a request directly — a
 * restore this size has no more place inside a web request's own time
 * budget than producing the backup itself does (see
 * {@see DatabaseBackupJob}).
 *
 * Both outcomes are recorded from inside this job, following
 * {@see DispatchNotificationJob}'s own established shape for a queued unit
 * that must record its own result: a journal entry naming the acting
 * administrator and the restored artefact's own timestamp either way, plus
 * a notification through the platform's own notification model, using the
 * same "notify every holder of the relevant permission" pattern the
 * scheduled-backup failure listener already established for a different
 * permission. There is no Spatie backup event to lean on here — restoring
 * is never something Spatie's own package performs.
 */
final class DatabaseRestoreJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        private readonly string $backupPath,
        private readonly int $actorId,
    ) {
        // Same "backups" queue as the jobs that produce what this one
        // consumes — see config/horizon.php's own supervisor, deliberately
        // single-process and single-try so a retried restore can never
        // interleave with a fresh attempt against the same database.
        $this->onQueue('backups');
    }

    public function handle(DatabaseRestoreService $restores, AuditJournal $journal): void
    {
        $actor = User::query()->find($this->actorId);

        if (! $actor instanceof User) {
            return;
        }

        $timestamp = $restores->find($this->backupPath)?->date()->toDateTimeString() ?? $this->backupPath;

        try {
            $restores->restore($this->backupPath);
        } catch (Throwable $exception) {
            $this->recordOutcome($journal, $actor, 'backup_restore_failed', $timestamp, $exception->getMessage());
            $this->notifyAdministrators(
                'Database restore failed',
                "Restoring the backup taken at {$timestamp} failed: {$exception->getMessage()}",
            );

            throw $exception;
        }

        $this->recordOutcome($journal, $actor, 'backup_restored', $timestamp, null);
        $this->notifyAdministrators(
            'Database restore completed',
            "The database was restored from the backup taken at {$timestamp}, triggered by {$actor->name}.",
        );
    }

    private function recordOutcome(AuditJournal $journal, User $actor, string $event, string $timestamp, ?string $error): void
    {
        $newValues = [
            'backup_path' => $this->backupPath,
            'backup_timestamp' => $timestamp,
        ];

        if ($error !== null) {
            $newValues['error'] = $error;
        }

        // The acting administrator is the journal's target, the same
        // convention {@see AuditJournal}'s own docblock names for an event
        // with no other natural Eloquent subject ("a sign-in targets the
        // account itself") — a whole-database restore has no single row of
        // its own to name.
        $journal->record($event, $actor, [], $newValues, $actor, ['backup', 'restore']);
    }

    /**
     * Every account holding `backup_restore` — the same administrator-only
     * gate the restore screen itself checks, so whoever could have
     * triggered this is exactly who is told how it went.
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

    /**
     * A plain relationship filter that degrades to an empty collection
     * rather than throwing when the permission has not been seeded in a
     * given environment or fixture — the same choice the scheduled-backup
     * failure listener already made for the equivalent backup-failure
     * audience.
     *
     * @return Collection<int, User>
     */
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
