<?php

declare(strict_types=1);

namespace App\Services\Audit;

/**
 * Journal entries carry no dedicated outcome column — a failure is already
 * distinguishable by its event name (`sign_in_failed` versus `sign_in`), the
 * convention every journal-writing listener in this codebase already
 * follows. This derives the outcome from that name rather than adding a
 * redundant column that could drift from it.
 */
final class AuditPresenter
{
    private const array FAILURE_SUFFIXES = ['_failed', '_refused', '_denied', '_rejected'];

    public static function outcome(string $event): string
    {
        return self::isFailure($event) ? 'failure' : 'success';
    }

    public static function isFailure(string $event): bool
    {
        foreach (self::FAILURE_SUFFIXES as $suffix) {
            if (str_ends_with($event, $suffix)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    public static function failureSuffixes(): array
    {
        return self::FAILURE_SUFFIXES;
    }
}
