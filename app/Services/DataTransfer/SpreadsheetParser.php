<?php

declare(strict_types=1);

namespace App\Services\DataTransfer;

use DateInterval;
use DateTimeInterface;
use Filament\Actions\ImportAction;
use InvalidArgumentException;
use League\Csv\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * Reads an uploaded import file into an in-memory row set, independent of
 * Filament's own import machinery — {@see ImportAction}
 * reads CSV only, while this back office's own import screen additionally
 * accepts XLSX, the format most operators actually export their source
 * data in. Both readers stop at the header row plus every non-blank data
 * row that follows it, on the first sheet only.
 */
final class SpreadsheetParser
{
    public function parse(string $absolutePath, string $extension): ParsedSpreadsheet
    {
        return match (strtolower($extension)) {
            'xlsx' => $this->parseXlsx($absolutePath),
            'csv' => $this->parseCsv($absolutePath),
            default => throw new InvalidArgumentException("Unsupported import file extension [{$extension}]. Use xlsx or csv."),
        };
    }

    private function parseXlsx(string $path): ParsedSpreadsheet
    {
        $reader = new XlsxReader;
        $reader->open($path);

        $headers = [];
        $rows = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            $isHeaderRow = true;

            foreach ($sheet->getRowIterator() as $row) {
                $values = $row->toArray();

                if ($isHeaderRow) {
                    $headers = array_map(self::cellToString(...), $values);
                    $isHeaderRow = false;

                    continue;
                }

                if ($this->isBlankRow($values)) {
                    continue;
                }

                $rows[] = $this->combine($headers, $values);
            }

            // Only the first sheet is a data source — a spreadsheet with
            // supplementary sheets (notes, instructions) is a real shape an
            // operator's own tool produces by default, not a malformed file.
            break;
        }

        $reader->close();

        return new ParsedSpreadsheet($headers, $rows);
    }

    private function parseCsv(string $path): ParsedSpreadsheet
    {
        $csv = CsvReader::createFromPath($path, 'r');
        $csv->setHeaderOffset(0);

        $headers = array_values($csv->getHeader());
        $rows = [];

        foreach ($csv->getRecords() as $record) {
            /** @var array<string, mixed> $record */
            if ($this->isBlankRow(array_values($record))) {
                continue;
            }

            $rows[] = $record;
        }

        return new ParsedSpreadsheet($headers, $rows);
    }

    /**
     * A header cell is expected to be plain text, but openspout types every
     * cell value generically (a date-formatted header cell decodes to a
     * real `DateTimeInterface`, not a string) — normalized here so the rest
     * of the pipeline can treat every header as a plain string.
     */
    private static function cellToString(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if ($value instanceof DateInterval) {
            return $value->format('%r%y-%m-%d %h:%i:%s');
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return trim((string) $value);
    }

    /** @param  list<mixed>  $values */
    private function isBlankRow(array $values): bool
    {
        foreach ($values as $value) {
            if ($value !== null && $value !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<mixed>  $values
     * @return array<string, mixed>
     */
    private function combine(array $headers, array $values): array
    {
        $row = [];

        foreach ($headers as $index => $header) {
            if ($header === '') {
                continue;
            }

            $row[$header] = $values[$index] ?? null;
        }

        return $row;
    }
}
