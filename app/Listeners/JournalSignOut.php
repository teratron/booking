<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\User;
use App\Services\Audit\AuditJournal;
use Illuminate\Auth\Events\Logout;

final class JournalSignOut
{
    public function __construct(private readonly AuditJournal $journal) {}

    public function handle(Logout $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $this->journal->record(
            event: 'sign_out',
            target: $event->user,
            newValues: ['guard' => $event->guard],
            actor: $event->user,
            tags: ['authentication'],
        );
    }
}
