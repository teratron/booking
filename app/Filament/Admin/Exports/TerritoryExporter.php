<?php

declare(strict_types=1);

namespace App\Filament\Admin\Exports;

use App\Filament\Admin\Exports\Concerns\ReadsTransferableRegistry;
use Filament\Actions\Exports\Exporter;

/**
 * Exports whichever territory rows the geographic reference data list's
 * active filter set currently resolves to. Column set, model, and formats
 * all come from the transferable data-type registry's `geography` entry.
 */
final class TerritoryExporter extends Exporter
{
    use ReadsTransferableRegistry;

    public static function transferableKey(): string
    {
        return 'geography';
    }
}
