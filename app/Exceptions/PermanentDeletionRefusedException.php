<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class PermanentDeletionRefusedException extends RuntimeException
{
    public static function notChiefAdministrator(int $objectId): self
    {
        return new self("Object [{$objectId}] cannot be permanently deleted — this action is restricted to the chief administrator.");
    }

    public static function passwordMismatch(int $objectId): self
    {
        return new self("Object [{$objectId}] was not permanently deleted — the re-authentication password did not match.");
    }

    public static function notArchived(int $objectId): self
    {
        return new self("Object [{$objectId}] must be archived before it can be permanently deleted.");
    }
}
