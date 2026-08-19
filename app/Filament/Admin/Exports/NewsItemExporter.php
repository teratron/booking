<?php

declare(strict_types=1);

namespace App\Filament\Admin\Exports;

use App\Filament\Admin\Exports\Concerns\ReadsTransferableRegistry;
use Filament\Actions\Exports\Exporter;

/**
 * Exports whichever news item rows the news list's active filter set
 * currently resolves to. Column set, model, and formats all come from the
 * transferable data-type registry's `news` entry.
 */
final class NewsItemExporter extends Exporter
{
    use ReadsTransferableRegistry;

    public static function transferableKey(): string
    {
        return 'news';
    }
}
