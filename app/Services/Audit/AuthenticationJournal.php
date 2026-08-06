<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Models\User;

/**
 * Sign-in events that no framework event announces, and which therefore have
 * to be recorded from the page that observes them.
 */
final class AuthenticationJournal
{
    public function __construct(private readonly AuditJournal $journal) {}

    /**
     * Records a throttle refusal against the account whose address was
     * submitted.
     *
     * Attributed to that account, when the address matches one. A lockout on an
     * address matching no account is a scan rather than an attack on a
     * principal, and is left to the application log — the journal is keyed to a
     * target record, and an unauthenticated visitor must not be able to append
     * rows to a table no role is permitted to prune.
     */
    public function recordLockout(?string $email, int $secondsUntilAvailable): void
    {
        if (! is_string($email) || $email === '') {
            return;
        }

        $user = User::query()->where('email', $email)->first();

        if (! $user instanceof User) {
            return;
        }

        $this->journal->record(
            event: 'sign_in_locked_out',
            target: $user,
            newValues: ['seconds_until_available' => $secondsUntilAvailable],
            actor: $user,
            tags: ['authentication'],
        );
    }
}
