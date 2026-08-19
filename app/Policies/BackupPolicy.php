<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Guards the single most destructive action the staff panel offers.
 * Restoring overwrites the running database outright, so this is
 * deliberately its own gate rather than a reuse of `settings_management` —
 * every role that can see the backup administration screen (including
 * `technical_support`) may still be unable to restore one.
 *
 * Bound against `Spatie\Backup\BackupDestination\Backup` rather than an
 * `App\Models` class, since Spatie's own artefact object is what the
 * restore screen and job both already type against — the same manual
 * `Gate::policy()` registration `AuditPolicy` already needed for a
 * vendor model this project does not own.
 */
final class BackupPolicy
{
    public function restore(User $user): bool
    {
        return $user->can('backup_restore');
    }
}
