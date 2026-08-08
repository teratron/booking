<?php

declare(strict_types=1);

namespace App\Services\Owners;

use App\Exceptions\ImpersonationRefusedException;
use App\Models\User;
use App\Services\Audit\AuditJournal;
use App\Services\Audit\ImpersonationContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * Support-mode impersonation — the single most sensitive capability in this
 * panel, since it grants an administrator the full authority of another
 * account. Its journal record is deliberately unconditional: the event is
 * written before the session switches, and a failure in the switch itself
 * cannot un-write it.
 */
final class ImpersonationService
{
    public function __construct(private readonly AuditJournal $journal) {}

    /**
     * Authenticates $actor as $owner in the panel session, so support mode
     * can proceed through the cabinet exactly as the owner would see it.
     *
     * @throws ImpersonationRefusedException when the actor lacks the
     *                                       permission, the actor is itself an owner-scoped account, or the
     *                                       target does not hold the owner role
     */
    public function enter(User $owner, User $actor): void
    {
        if (! $actor->can('impersonate')) {
            throw ImpersonationRefusedException::actorNotPermitted($actor->id);
        }

        if ($actor->hasRole('object_owner')) {
            throw ImpersonationRefusedException::actorIsOwnerScoped($actor->id);
        }

        if (! $owner->hasRole('object_owner')) {
            throw ImpersonationRefusedException::targetIsNotAnOwner($owner->id);
        }

        // Written and committed before anything about the session changes,
        // and outside any transaction the switch below could roll back —
        // the record survives even if the switch that follows does not.
        $this->journal->record(
            'owner_impersonation_started',
            $owner,
            [],
            ['impersonator_id' => $actor->id, 'target_owner_id' => $owner->id],
            $actor,
            ['owner', 'impersonation'],
        );

        Session::put(ImpersonationContext::SESSION_KEY, $actor->id);
        Auth::guard('web')->loginUsingId($owner->id);
    }

    /**
     * Restores the administrator's own identity. A no-op, not an error,
     * when support mode is not currently active — the same idempotent
     * treatment {@see OwnerAccountService::block()} already gives its own
     * not-currently-in-that-state case.
     */
    public function exit(): void
    {
        $impersonatorId = Session::get(ImpersonationContext::SESSION_KEY);

        if (! is_int($impersonatorId)) {
            return;
        }

        $administrator = User::query()->find($impersonatorId);
        $impersonatedOwner = Auth::guard('web')->user();

        if ($administrator === null) {
            Session::forget(ImpersonationContext::SESSION_KEY);

            return;
        }

        if ($impersonatedOwner instanceof User) {
            $this->journal->record(
                'owner_impersonation_ended',
                $impersonatedOwner,
                [],
                [],
                $administrator,
                ['owner', 'impersonation'],
            );
        }

        Session::forget(ImpersonationContext::SESSION_KEY);
        Auth::guard('web')->login($administrator);
    }
}
