<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when any record in a bulk selection sits outside the acting
 * administrator's scope or policy grant.
 *
 * The whole batch is refused, never a partial subset — an administrator who
 * sees "41 of 42 objects updated" has no way to tell, from that message
 * alone, which one was skipped or why, and a silently-shrunk selection is
 * exactly the kind of surprise a counted confirmation exists to prevent.
 */
final class BulkSelectionScopeException extends RuntimeException
{
    private function __construct(
        string $message,
        public readonly int $outOfScopeCount,
        public readonly int $totalCount,
    ) {
        parent::__construct($message);
    }

    public static function forSelection(int $outOfScopeCount, int $totalCount): self
    {
        return new self(
            "{$outOfScopeCount} of {$totalCount} selected objects fall outside the actor's permitted scope; no bulk action was applied to any of them.",
            $outOfScopeCount,
            $totalCount,
        );
    }
}
