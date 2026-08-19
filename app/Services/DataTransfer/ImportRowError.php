<?php

declare(strict_types=1);

namespace App\Services\DataTransfer;

/**
 * One row that failed validation during the preview pass — the row's
 * 1-based position among the data rows (the header excluded, so "row 1" is
 * the first row of actual data) and every validation message it produced.
 */
final readonly class ImportRowError
{
    /** @param  list<string>  $messages */
    public function __construct(
        public int $row,
        public array $messages,
    ) {}
}
