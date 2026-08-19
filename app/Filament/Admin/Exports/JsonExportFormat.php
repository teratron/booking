<?php

declare(strict_types=1);

namespace App\Filament\Admin\Exports;

use Filament\Actions\Action;
use Filament\Actions\Exports\Downloaders\Contracts\Downloader;
use Filament\Actions\Exports\Enums\Contracts\ExportFormat as ExportFormatContract;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Facades\URL;

/**
 * A third download format alongside Filament's own CSV and XLSX. Filament's
 * queued export pipeline writes CSV chunk files to disk regardless of which
 * formats are declared, and its own XLSX download is itself only a
 * download-time conversion of those chunks — this format follows the
 * identical pattern, converting to JSON instead, via a route this panel
 * registers itself rather than one Filament ships.
 */
enum JsonExportFormat: string implements ExportFormatContract
{
    case Json = 'json';

    public function getDownloader(): Downloader
    {
        return app(JsonDownloader::class);
    }

    public function getDownloadNotificationAction(Export $export, string $authGuard): Action
    {
        return Action::make('download_json')
            ->label(__('panel.data_transfer.export.actions.download_json'))
            ->url(
                URL::signedRoute('exports.download-json', ['authGuard' => $authGuard, 'export' => $export], absolute: false),
                shouldOpenInNewTab: true,
            )
            ->markAsRead();
    }
}
