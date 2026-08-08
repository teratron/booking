<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class LanguageAdministrationRefusedException extends RuntimeException
{
    public static function cannotDeactivatePrimary(int $languageId): self
    {
        return new self("Language [{$languageId}] is the primary language and cannot be deactivated — set a different primary language first.");
    }

    public static function cannotDeactivateLastActive(int $languageId): self
    {
        return new self("Language [{$languageId}] is the last active language — the portal must always have at least one active language.");
    }

    public static function mustBeActiveToBecomePrimary(int $languageId): self
    {
        return new self("Language [{$languageId}] must be activated before it can become the primary language.");
    }
}
