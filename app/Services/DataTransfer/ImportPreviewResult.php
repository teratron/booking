<?php

declare(strict_types=1);

namespace App\Services\DataTransfer;

/**
 * What a dry-run validation pass found, without writing anything: how many
 * of the previewed rows would create a new record, how many would update an
 * existing one, the full list of rows that failed validation, and every row
 * that would create a new object but plausibly duplicates one already in the
 * catalog.
 */
final readonly class ImportPreviewResult
{
    /**
     * @param  list<ImportRowError>  $errors
     * @param  list<ImportRowDuplicate>  $duplicates
     */
    public function __construct(
        public int $totalRows,
        public int $createCount,
        public int $updateCount,
        public array $errors,
        public array $duplicates = [],
    ) {}

    public function errorCount(): int
    {
        return count($this->errors);
    }

    public function duplicateCount(): int
    {
        return count($this->duplicates);
    }
}
