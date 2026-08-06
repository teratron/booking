<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\User;
use App\Services\Audit\AuditJournal;
use Illuminate\Auth\Events\Login;

/**
 * Thin adapter: the framework event is translated into a journal entry and
 * nothing else happens here. The writing itself belongs to the service.
 */
final class JournalSuccessfulSignIn
{
    public function __construct(private readonly AuditJournal $journal) {}

    public function handle(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $this->journal->record(
            event: 'sign_in',
            target: $event->user,
            newValues: ['guard' => $event->guard, 'remembered' => $event->remember],
            actor: $event->user,
            tags: ['authentication'],
        );
    }
}
