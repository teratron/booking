<?php

declare(strict_types=1);

namespace App\Services\Audit;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;
use OwenIt\Auditing\Models\Audit;

/**
 * Writes the journal entries Eloquent auditing cannot observe.
 *
 * `owen-it/laravel-auditing` records model mutations by listening to Eloquent
 * events, which covers created / updated / deleted and nothing else. A large
 * share of what the governance requirements ask this journal to hold is not a
 * model mutation at all: signing in, signing out, being locked out, entering
 * an owner's cabinet in support mode, exporting a filtered data set, toggling
 * a feature module. Those go through here.
 *
 * It is the same journal, not a second one. A moderation decision and a
 * sign-in are both privileged events, and splitting them across two tables
 * would leave two partial histories where the requirement asks for one.
 */
final class AuditJournal
{
    public function __construct(private readonly ImpersonationContext $impersonationContext) {}

    /**
     * Appends one entry to the action journal.
     *
     * The entry carries the acting account (falling back to whoever is
     * really behind the current session — the authenticated user, or the
     * administrator when impersonating), the request's IP address and user
     * agent, and whatever before/after values the caller can supply. Never
     * throws for a missing request context — journal writes happen from
     * queued jobs and console commands too.
     *
     * @param  string  $event  verb recorded against the entry, e.g. `sign_in`, `impersonation_started`
     * @param  Model  $target  the record the event concerns; a sign-in targets the account itself
     * @param  array<string, mixed>  $oldValues  state before the event, empty when the event has no prior state
     * @param  array<string, mixed>  $newValues  state after the event, or context describing it
     * @param  list<string>  $tags  free-form labels for filtering the journal
     */
    public function record(
        string $event,
        Model $target,
        array $oldValues = [],
        array $newValues = [],
        ?Authenticatable $actor = null,
        array $tags = [],
    ): Audit {
        // Not a plain auth()->user() fallback: while impersonating, the
        // guard's own current user is the impersonated owner, and this
        // journal must attribute an unspecified actor to whoever is really
        // acting instead.
        $actor ??= $this->impersonationContext->currentActor();

        $audit = new Audit;

        $audit->forceFill([
            'user_type' => $actor === null ? null : $actor::class,
            'user_id' => $actor?->getAuthIdentifier(),
            'event' => $event,
            'auditable_type' => $target::class,
            'auditable_id' => $target->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'url' => $this->url(),
            'ip_address' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 1023),
            'tags' => $tags === [] ? null : implode(',', $tags),
        ]);

        $audit->save();

        return $audit;
    }

    /**
     * The console has no URL, and `fullUrl()` on a console request returns a
     * synthetic value that would read as a real page in the journal.
     */
    private function url(): ?string
    {
        return app()->runningInConsole() ? null : Request::fullUrl();
    }
}
