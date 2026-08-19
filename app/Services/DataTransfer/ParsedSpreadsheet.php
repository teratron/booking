<?php

declare(strict_types=1);

namespace App\Services\DataTransfer;

use Filament\Actions\Imports\Importer;

/**
 * The result of reading an uploaded import file into memory: the header row
 * as declared by the file itself, and every data row keyed by that same
 * header — the shape both the column-mapping step and
 * {@see Importer} expect a raw row to be in before
 * the operator's column map is applied.
 */
final readonly class ParsedSpreadsheet
{
    /**
     * @param  list<string>  $headers
     * @param  list<array<string, mixed>>  $rows
     */
    public function __construct(
        public array $headers,
        public array $rows,
    ) {}
}
