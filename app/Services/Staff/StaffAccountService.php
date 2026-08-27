<?php

declare(strict_types=1);

namespace App\Services\Staff;

use App\Exceptions\UnrevocableGrantException;
use App\Models\User;
use App\Services\Audit\AuditJournal;
use App\Services\Authorization\RoleGrantService;
use App\Services\Owners\OwnerAccountService;
use Illuminate\Support\Facades\DB;

/**
 * The staff-administration screen's own lifecycle actions — creating an
 * administrative account, editing its contact details, and deactivating or
 * restoring its panel access. Mirrors {@see OwnerAccountService}'s
 * shape for the same reason: these are administrative actions with their
 * own audit trail, not form saves.
 *
 * Deactivation reuses `users.blocked_at` / `blocked_by` rather than a
 * second, staff-specific pair of columns — {@see User::canAccessPanel()}
 * already refuses admission to both panels for any blocked account, so a
 * deactivated staff member loses access through the same mechanism an
 * owner does, with no extra branch to keep in sync. Deletion is never an
 * option here: the specification requires an account's journal entries to
 * outlive the account, and a deleted row takes its own history with it.
 */
final class StaffAccountService
{
    public function __construct(
        private readonly AuditJournal $journal,
        private readonly RoleGrantService $roleGrantService,
    ) {}

    /**
     * Creates a new staff account on the chief administrator's behalf.
     *
     * Unlike {@see OwnerAccountService::createAccount()},
     * the acting administrator sets the initial password directly — a
     * staff account is handed to a colleague, not a self-registering
     * visitor, so there is no reset-link handoff to perform.
     *
     * @param  array{name: string, email: string, password: string}  $data
     */
    public function createAccount(array $data, User $actor): User
    {
        return DB::transaction(function () use ($data, $actor): User {
            $staff = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            $this->journal->record(
                'staff_account_created',
                $staff,
                [],
                ['name' => $staff->name, 'email' => $staff->email],
                $actor,
                ['staff'],
            );

            return $staff;
        });
    }

    /**
     * Updates a staff account's contact details. Only the fields present in
     * $data are considered, and only fields whose value actually changes
     * are recorded — an update that touches nothing writes no journal
     * entry.
     *
     * @param  array{name?: string, email?: string}  $data
     */
    public function updateContacts(User $staff, array $data, User $actor): void
    {
        $candidates = array_intersect_key($data, array_flip(['name', 'email']));

        $old = [];
        $new = [];

        foreach ($candidates as $field => $value) {
            if ($staff->getAttribute($field) !== $value) {
                $old[$field] = $staff->getAttribute($field);
                $new[$field] = $value;
            }
        }

        if ($new === []) {
            return;
        }

        $staff->forceFill($new)->save();

        $this->journal->record('staff_contacts_updated', $staff, $old, $new, $actor, ['staff']);
    }

    /**
     * Deactivates $staff's account access. Idempotent — deactivating an
     * already-deactivated account is a no-op, not an error, so a retried or
     * doubled request never produces a duplicate journal entry.
     *
     * @throws UnrevocableGrantException if $staff is the chief
     *                                   administrator role's last remaining holder able to sign in — the
     *                                   guard lives here, in the write path, rather than in the screen
     *                                   that calls it, so no caller can bypass it by skipping a check the
     *                                   screen alone would have made.
     */
    public function deactivate(User $staff, User $actor): void
    {
        if ($staff->blocked_at !== null) {
            return;
        }

        $this->roleGrantService->guardDeactivation($staff);

        $staff->forceFill(['blocked_at' => now(), 'blocked_by' => $actor->id])->save();

        $this->journal->record(
            'staff_deactivated',
            $staff,
            ['blocked_at' => null, 'blocked_by' => null],
            ['blocked_at' => $staff->blocked_at, 'blocked_by' => $actor->id],
            $actor,
            ['staff'],
        );
    }

    /**
     * Restores a deactivated account's access. Idempotent for the same
     * reason {@see deactivate()} is.
     */
    public function restore(User $staff, User $actor): void
    {
        if ($staff->blocked_at === null) {
            return;
        }

        $previous = ['blocked_at' => $staff->blocked_at, 'blocked_by' => $staff->blocked_by];

        $staff->forceFill(['blocked_at' => null, 'blocked_by' => null])->save();

        $this->journal->record('staff_restored', $staff, $previous, ['blocked_at' => null, 'blocked_by' => null], $actor, ['staff']);
    }
}
