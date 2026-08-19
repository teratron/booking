<?php

declare(strict_types=1);

namespace App\Filament\Admin\Exports;

use Filament\Actions\Exports\Downloaders\Contracts\Downloader;
use Filament\Actions\Exports\Models\Export;
use League\Csv\Reader as CsvReader;
use League\Csv\Statement;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Converts the same CSV chunk files Filament's own queued export pipeline
 * already wrote to disk — the header row plus one file per processed
 * chunk — into a JSON array of objects, streamed directly to the browser.
 * Mirrors `Filament\Actions\Exports\Downloaders\XlsxDownloader`'s own
 * on-download conversion of those chunks, for a format Filament does not
 * ship.
 */
final class JsonDownloader implements Downloader
{
    public function __invoke(Export $export): StreamedResponse
    {
        $disk = $export->getFileDisk();
        $directory = $export->getFileDirectory();

        if (! $disk->exists($directory)) {
            abort(404);
        }

        $csvDelimiter = $export->exporter::getCsvDelimiter();

        $readRows = function (string $file) use ($csvDelimiter, $disk): iterable {
            $stream = $disk->readStream($file);

            if ($stream === null) {
                return [];
            }

            $csvReader = CsvReader::from($stream);
            $csvReader->setDelimiter($csvDelimiter);

            return (new Statement)->process($csvReader)->getRecords();
        };

        $headers = [];

        foreach ($readRows($directory.DIRECTORY_SEPARATOR.'headers.csv') as $row) {
            $headers = array_values($row);

            break;
        }

        $fileName = "{$export->file_name}.json";

        return response()->streamDownload(function () use ($disk, $directory, $headers, $readRows): void {
            echo '[';

            $isFirstRow = true;

            foreach ($disk->files($directory) as $file) {
                if (str($file)->endsWith('headers.csv') || (! str($file)->endsWith('.csv'))) {
                    continue;
                }

                foreach ($readRows($file) as $row) {
                    $values = array_values($row);
                    $record = array_combine($headers, array_pad($values, count($headers), null));

                    echo ($isFirstRow ? '' : ',').json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                    $isFirstRow = false;

                    flush();
                }
            }

            echo ']';
        }, $fileName, [
            'Content-Type' => 'application/json',
        ]);
    }
}
