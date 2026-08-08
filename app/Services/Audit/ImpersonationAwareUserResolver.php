<?php

declare(strict_types=1);

namespace App\Services\Audit;

use Illuminate\Contracts\Auth\Authenticatable;
use OwenIt\Auditing\Contracts\UserResolver;

/**
 * `owen-it/laravel-auditing`'s user-resolver contract for its own,
 * automatic Eloquent-observed audits (created/updated/deleted) — a
 * separate code path from {@see AuditJournal}'s explicit event writes, and
 * one that needs the identical impersonation-aware attribution
 * {@see ImpersonationContext} already provides, or a model mutated while
 * impersonating would be silently attributed to the owner instead of the
 * administrator actually acting.
 *
 * The interface's `resolve()` is static with no constructor injection
 * available, so the container is asked directly here — the standard way
 * this package's static resolver contract is satisfied when the
 * resolution logic itself needs a service.
 */
final class ImpersonationAwareUserResolver implements UserResolver
{
    public static function resolve(): ?Authenticatable
    {
        return app(ImpersonationContext::class)->currentActor();
    }
}
