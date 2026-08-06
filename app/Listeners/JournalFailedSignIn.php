<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\User;
use App\Services\Audit\AuditJournal;
use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Facades\Log;

/**
 * A failed sign-in against a real account is the signal worth keeping: it is
 * what a credential-stuffing run against a known administrator looks like.
 *
 * A failure against an address that matches no account is deliberately NOT
 * journalled. The journal is keyed to a target record and an unknown address
 * has none, so writing it would mean inventing a placeholder target; worse,
 * an unauthenticated visitor could then fill the highest-privilege table in
 * the schema — one that no role is permitted to prune — at will. Those
 * attempts go to the application log, where retention is already bounded.
 */
final class JournalFailedSignIn
{
    public function __construct(private readonly AuditJournal $journal) {}

    public function handle(Failed $event): void
    {
        if (! $event->user instanceof User) {
            Log::channel('stack')->notice('Sign-in attempt for an unrecognised address.', [
                'email' => $event->credentials['email'] ?? null,
                'ip' => request()->ip(),
            ]);

            return;
        }

        $this->journal->record(
            event: 'sign_in_failed',
            target: $event->user,
            newValues: ['guard' => $event->guard],
            actor: $event->user,
            tags: ['authentication'],
        );
    }
}
