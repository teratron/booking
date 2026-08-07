<?php

declare(strict_types=1);

namespace App\Services\Owners;

use App\Exceptions\OwnerDetachmentException;
use App\Models\Object_;
use App\Models\User;
use App\Services\Audit\AuditJournal;
use App\Services\Objects\ObjectLifecycleService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * The object-owner management screen's own lifecycle actions — creating an
 * account on an owner's behalf, editing their contact details, attaching or
 * detaching the objects they own, and suspending or restoring account
 * access. Every method mutates immediately and journals what it did,
 * mirroring `ObjectLifecycleService`'s shape for the same reason: these are
 * administrative actions with their own audit trail, not form saves.
 */
final class OwnerAccountService
{
    public function __construct(
        private readonly AuditJournal $journal,
        private readonly ObjectLifecycleService $lifecycle,
    ) {}

    /**
     * Creates a new owner account on the administrator's behalf.
     *
     * The password is a strong random value the administrator never sees —
     * this is staff opening an account for someone else, not a
     * self-registration flow, so the real owner is immediately sent a
     * password-reset link and sets their own credential from there.
     *
     * @param  array{name: string, email: string, company?: ?string, phone?: ?string, country_id?: ?int}  $data
     */
    public function createAccount(array $data, User $actor): User
    {
        $owner = DB::transaction(function () use ($data, $actor): User {
            $owner = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Str::password(32),
                'company' => $data['company'] ?? null,
                'phone' => $data['phone'] ?? null,
                'country_id' => $data['country_id'] ?? null,
            ]);

            $owner->assignRole('object_owner');

            $this->journal->record(
                'owner_account_created',
                $owner,
                [],
                ['name' => $owner->name, 'email' => $owner->email],
                $actor,
                ['owner'],
            );

            return $owner;
        });

        $this->sendPasswordResetLink($owner, $actor);

        return $owner;
    }

    /**
     * Updates an owner's contact details. Only the fields present in $data
     * are considered, and only fields whose value actually changes are
     * recorded — an update that touches nothing writes no journal entry.
     *
     * @param  array{name?: string, email?: string, company?: ?string, phone?: ?string, country_id?: ?int}  $data
     */
    public function updateContacts(User $owner, array $data, User $actor): void
    {
        $candidates = array_intersect_key($data, array_flip(['name', 'email', 'company', 'phone', 'country_id']));

        $old = [];
        $new = [];

        foreach ($candidates as $field => $value) {
            if ($owner->getAttribute($field) !== $value) {
                $old[$field] = $owner->getAttribute($field);
                $new[$field] = $value;
            }
        }

        if ($new === []) {
            return;
        }

        $owner->forceFill($new)->save();

        $this->journal->record('owner_contacts_updated', $owner, $old, $new, $actor, ['owner']);
    }

    /**
     * Sets $object's owner to $owner. When the object already belonged to a
     * different owner this is a reassignment, delegated to
     * `ObjectLifecycleService::transferOwnership()` so its staff-grant
     * cleanup runs — that cleanup query assumes a previous owner existed, so
     * it is not safe to call when the object had none.
     */
    public function attachObject(User $owner, Object_ $object, User $actor): void
    {
        $previousOwnerId = $object->owner_id;

        DB::transaction(function () use ($owner, $object, $actor, $previousOwnerId): void {
            if ($previousOwnerId !== null && $previousOwnerId !== $owner->id) {
                $this->lifecycle->transferOwnership($object, $owner, $actor);
            } else {
                $object->forceFill(['owner_id' => $owner->id])->save();
            }

            $this->journal->record(
                'owner_object_attached',
                $object,
                ['owner_id' => $previousOwnerId],
                ['owner_id' => $owner->id],
                $actor,
                ['owner', 'object'],
            );
        });
    }

    /**
     * Clears $object's owner.
     *
     * @throws OwnerDetachmentException when the object has no owner already
     */
    public function detachObject(Object_ $object, User $actor): void
    {
        if ($object->owner_id === null) {
            throw OwnerDetachmentException::forObject($object->id);
        }

        $previousOwnerId = $object->owner_id;

        $object->forceFill(['owner_id' => null])->save();

        $this->journal->record(
            'owner_object_detached',
            $object,
            ['owner_id' => $previousOwnerId],
            ['owner_id' => null],
            $actor,
            ['owner', 'object'],
        );
    }

    /**
     * Suspends $owner's account access. Blocking an already-blocked account
     * is a no-op, not an error — idempotent by design, so a retried or
     * doubled request never produces a duplicate journal entry.
     */
    public function block(User $owner, User $actor): void
    {
        if ($owner->blocked_at !== null) {
            return;
        }

        $owner->forceFill(['blocked_at' => now(), 'blocked_by' => $actor->id])->save();

        $this->journal->record(
            'owner_blocked',
            $owner,
            ['blocked_at' => null, 'blocked_by' => null],
            ['blocked_at' => $owner->blocked_at, 'blocked_by' => $actor->id],
            $actor,
            ['owner'],
        );
    }

    /**
     * Lifts a suspension. Restoring an already-active account is a no-op,
     * not an error, for the same reason `block()` is idempotent.
     */
    public function restore(User $owner, User $actor): void
    {
        if ($owner->blocked_at === null) {
            return;
        }

        $previous = ['blocked_at' => $owner->blocked_at, 'blocked_by' => $owner->blocked_by];

        $owner->forceFill(['blocked_at' => null, 'blocked_by' => null])->save();

        $this->journal->record('owner_restored', $owner, $previous, ['blocked_at' => null, 'blocked_by' => null], $actor, ['owner']);
    }

    /**
     * Dispatches Laravel's own password-reset broker for $owner. No custom
     * reset-token mechanism — this project already ships the framework's
     * stock `password_reset_tokens` table, so the stock broker is used
     * rather than reinvented.
     */
    public function sendPasswordResetLink(User $owner, User $actor): void
    {
        Password::sendResetLink(['email' => $owner->email]);

        $this->journal->record('owner_password_reset_link_sent', $owner, [], ['email' => $owner->email], $actor, ['owner']);
    }
}
