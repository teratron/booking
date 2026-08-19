<?php

declare(strict_types=1);

namespace App\Services\DataTransfer;

use Illuminate\Database\Eloquent\Model;

/**
 * One entity kind eligible for import and export.
 *
 * The specification lists the same set of entities twice — once as import
 * targets, once as export targets. Declaring each kind here once, rather
 * than building the import pipeline and the export action against two
 * separately-maintained column lists, is what keeps them from drifting a
 * column apart: a column added to one reads from the same declaration the
 * other already consumes.
 */
final readonly class Transferable
{
    /**
     * @param  class-string<Model>  $model
     * @param  list<TransferableColumn>  $columns
     * @param  list<string>  $formats
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $model,
        public array $columns,
        public array $formats,
        public string $permission,
        public bool $requiresPersonalDataPermission = false,
        public bool $requiresFinancialPermission = false,
    ) {}

    /** @return list<string> */
    public function columnNames(): array
    {
        return array_map(static fn (TransferableColumn $column): string => $column->name, $this->columns);
    }

    /** @return list<string> */
    public function personalDataColumnNames(): array
    {
        return array_values(array_map(
            static fn (TransferableColumn $column): string => $column->name,
            array_filter($this->columns, static fn (TransferableColumn $column): bool => $column->isPersonalData),
        ));
    }

    /** @return list<string> */
    public function financialColumnNames(): array
    {
        return array_values(array_map(
            static fn (TransferableColumn $column): string => $column->name,
            array_filter($this->columns, static fn (TransferableColumn $column): bool => $column->isFinancial),
        ));
    }
}
