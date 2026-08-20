<?php

declare(strict_types=1);

namespace App\Services\Release;

/**
 * One destructive operation found in one migration file — the file it came
 * from and a plain-language description of what a straight rollback
 * (pinning back to the previous release's artefact and restarting) cannot
 * undo about it.
 */
final readonly class DestructiveMigrationFinding
{
    public function __construct(
        public string $file,
        public string $description,
    ) {}
}
