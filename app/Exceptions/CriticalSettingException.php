<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when an account without the chief administrator's authority attempts
 * to write a setting marked critical.
 *
 * Thrown from the repository rather than checked in the settings screen: the
 * requirement is about what an administrator *can* do, not about what the
 * interface offers them, and an import or a console command reaches the same
 * writer by a path no screen guards.
 */
final class CriticalSettingException extends RuntimeException
{
    public static function forKey(string $key): self
    {
        return new self("Setting [{$key}] is restricted to the chief administrator.");
    }
}
