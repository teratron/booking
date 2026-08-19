<?php

declare(strict_types=1);

namespace App\Services\DataTransfer;

use App\Filament\Admin\Imports\ObjectImporter;
use Filament\Actions\Imports\Importer;
use InvalidArgumentException;

/**
 * The subset of {@see TransferableRegistry} kinds this import pipeline can
 * actually write, keyed to the Filament {@see Importer} subclass that reads
 * that kind's declared columns and knows how to resolve or create one of
 * its records.
 *
 * A key present in {@see TransferableRegistry} but absent here is a kind the
 * registry declares for export but that import does not yet write end to
 * end — an explicit, honest boundary rather than a silent no-op the operator
 * would only discover after uploading a file. Wiring a new kind is adding
 * one entry here plus its own `Importer` subclass, not rewriting the
 * pipeline that reads this list.
 */
final class ImportKindRegistry
{
    /** @return array<string, class-string<Importer>> */
    public static function all(): array
    {
        return [
            'objects' => ObjectImporter::class,
        ];
    }

    public static function isSupported(string $kind): bool
    {
        return array_key_exists($kind, self::all());
    }

    /** @return class-string<Importer> */
    public static function importerFor(string $kind): string
    {
        return self::all()[$kind]
            ?? throw new InvalidArgumentException("Import is not yet implemented for data-type key [{$kind}].");
    }
}
