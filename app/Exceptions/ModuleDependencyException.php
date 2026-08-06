<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when enabling a module would produce a half-enabled capability —
 * the module itself on, something it needs off.
 *
 * Refused rather than silently resolved to disabled: an administrator who
 * switches a module on and sees it stay off has been told nothing about why,
 * and will switch it again.
 */
final class ModuleDependencyException extends RuntimeException
{
    public static function unmet(string $moduleKey, string $dependencyKey): self
    {
        return new self("Module [{$moduleKey}] cannot be enabled while [{$dependencyKey}] is disabled at this scope.");
    }

    public static function conflicting(string $moduleKey, string $conflictKey): self
    {
        return new self("Module [{$moduleKey}] conflicts with [{$conflictKey}], which is enabled at this scope.");
    }
}
