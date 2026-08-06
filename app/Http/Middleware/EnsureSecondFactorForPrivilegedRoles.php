<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Filament\Auth\MultiFactor\Http\Middleware\EnsureMultiFactorAuthenticationIsEnabled;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Override;

/**
 * Makes the second factor mandatory for the roles that configuration names,
 * and available-but-optional for everyone else.
 *
 * The panel toolkit models this as a panel-wide boolean, because the decision
 * is baked into route middleware at registration time and cannot see a user.
 * The requirement is narrower: the chief administrator holds every permission
 * including the one that edits permissions, so that credential alone is total
 * compromise, while a content manager's is not. Enforcement therefore moves
 * into the request, where the actor is known.
 */
final class EnsureSecondFactorForPrivilegedRoles extends EnsureMultiFactorAuthenticationIsEnabled
{
    #[Override]
    public function handle(Request $request, Closure $next): mixed
    {
        $user = Filament::auth()->user();

        /** @var list<string> $requiredForRoles */
        $requiredForRoles = config('booking.two_factor.required_for_roles', []);

        if (! $user instanceof User || $requiredForRoles === [] || ! $user->hasAnyRole($requiredForRoles)) {
            return $next($request);
        }

        return parent::handle($request, $next);
    }
}
