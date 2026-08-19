<?php

declare(strict_types=1);

namespace App\Services\DataTransfer;

/**
 * One preview row that would create a new object, alongside every existing
 * object {@see DuplicateDetectionService} flagged as a plausible match for
 * it. Surfaced next to {@see ImportRowError} in {@see ImportPreviewResult}
 * so an operator sees both what would go wrong and what might already
 * exist, before confirming anything.
 */
final readonly class ImportRowDuplicate
{
    /** @param  list<DuplicateCandidate>  $candidates */
    public function __construct(
        public int $row,
        public array $candidates,
    ) {}
}
