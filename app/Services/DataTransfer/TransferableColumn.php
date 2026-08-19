<?php

declare(strict_types=1);

namespace App\Services\DataTransfer;

/**
 * One column of a {@see Transferable} entity.
 *
 * The unit both the import column-mapping step and the export action read
 * to decide what a row carries: its label, its scalar type for validation,
 * and whether moving it requires the personal-data or financial permission
 * on top of the entity's own general export permission.
 */
final readonly class TransferableColumn
{
    public function __construct(
        public string $name,
        public string $label,
        public string $type,
        public bool $isPersonalData = false,
        public bool $isFinancial = false,
    ) {}
}
