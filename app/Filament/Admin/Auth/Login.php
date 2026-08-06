<?php

declare(strict_types=1);

namespace App\Filament\Admin\Auth;

use App\Services\Audit\AuthenticationJournal;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Notifications\Notification;
use Override;

/**
 * The staff panel's sign-in page, extended for one reason: a lockout has to
 * reach the action journal.
 *
 * Laravel fires an event for a successful sign-in, a sign-out, and a failed
 * attempt, and listeners journal all three. It fires nothing when a throttle
 * refuses the attempt, because the throttle lives in the page rather than in
 * the guard — so the one event that indicates an account is actively under
 * attack would be the one event with no record. This hook is called only on
 * that path.
 */
final class Login extends BaseLogin
{
    #[Override]
    protected function getRateLimitedNotification(TooManyRequestsException $exception): ?Notification
    {
        $email = $this->data['email'] ?? null;

        app(AuthenticationJournal::class)->recordLockout(
            is_string($email) ? $email : null,
            $exception->secondsUntilAvailable,
        );

        return parent::getRateLimitedNotification($exception);
    }
}
