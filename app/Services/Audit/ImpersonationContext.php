<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Models\User;
use App\Services\Owners\ImpersonationService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;

/**
 * The one place both journal-writing paths ask "who is really behind this
 * session" — {@see AuditJournal} and {@see ImpersonationAwareUserResolver}
 * (`owen-it/laravel-auditing`'s own resolver) each need the identical answer,
 * and a second, independent re-implementation of the same lookup is exactly
 * how the two would eventually drift and misattribute a mutation.
 *
 * While an administrator is impersonating an owner, the auth guard reports
 * the owner as the current user — that is the entire mechanism impersonation
 * relies on for panel access — so a plain `auth()->user()` read during that
 * session would misattribute every journal entry to the owner instead of the
 * administrator actually acting. The session marker this class reads is set
 * only by a real {@see ImpersonationService::enter()}
 * call and is what makes the correct attribution possible.
 */
final class ImpersonationContext
{
    public const string SESSION_KEY = 'impersonator_id';

    /**
     * The real actor behind the current request: the administrator when
     * impersonating, or the plainly-authenticated user otherwise.
     */
    public function currentActor(): ?Authenticatable
    {
        $impersonatorId = Session::get(self::SESSION_KEY);

        if (is_int($impersonatorId)) {
            return User::query()->find($impersonatorId);
        }

        return $this->authenticatedUser();
    }

    /**
     * Mirrors `OwenIt\Auditing\Resolvers\UserResolver::resolve()`'s own
     * guard-iteration fallback exactly, so the impersonation-aware path and
     * the package's own default behave identically once impersonation is not
     * in play.
     */
    private function authenticatedUser(): ?Authenticatable
    {
        $guards = Config::get('audit.user.guards', [Config::get('auth.defaults.guard')]);

        foreach ($guards as $guard) {
            try {
                $authenticated = Auth::guard($guard)->check();
            } catch (\Exception) {
                continue;
            }

            if ($authenticated === true) {
                return Auth::guard($guard)->user();
            }
        }

        return null;
    }
}
