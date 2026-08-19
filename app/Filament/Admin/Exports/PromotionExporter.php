<?php

declare(strict_types=1);

namespace App\Filament\Admin\Exports;

use App\Filament\Admin\Exports\Concerns\ReadsTransferableRegistry;
use Filament\Actions\Exports\Exporter;

/**
 * Exports whichever promotion rows the promotion list's active filter set
 * currently resolves to. Column set, model, and formats all come from the
 * transferable data-type registry's `promotions` entry.
 */
final class PromotionExporter extends Exporter
{
    use ReadsTransferableRegistry;

    public static function transferableKey(): string
    {
        return 'promotions';
    }
}
