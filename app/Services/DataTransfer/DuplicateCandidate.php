<?php

declare(strict_types=1);

namespace App\Services\DataTransfer;

/**
 * One existing object {@see DuplicateDetectionService} judged a plausible
 * match for an incoming import row, together with every signal that fired
 * against it. A candidate is never a merge — it is a pairing an
 * administrator still has to confirm before anything about the existing
 * catalog changes.
 */
final readonly class DuplicateCandidate
{
    /** @param  list<DuplicateSignal>  $signals */
    public function __construct(
        public int $objectId,
        public string $label,
        public array $signals,
    ) {}

    /** @return list<string> */
    public function signalKinds(): array
    {
        return array_values(array_unique(array_map(
            static fn (DuplicateSignal $signal): string => $signal->kind,
            $this->signals,
        )));
    }
}
