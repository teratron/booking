<?php

declare(strict_types=1);

use App\Services\DataTransfer\Transferable;
use App\Services\DataTransfer\TransferableRegistry;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Transferable Data-Type Registry
|--------------------------------------------------------------------------
|
| The registry every import and export path reads instead of maintaining
| its own column inventory. These assertions are what make that promise
| mechanical: a declared kind that stops resolving to a real model, or a
| declared column that stops existing on that model's table, fails here
| rather than surfacing as an empty column in a shipped export.
|
*/

it('declares exactly the thirteen kinds the specification names for both import and export', function (): void {
    expect(array_keys(TransferableRegistry::all()))->toEqualCanonicalizing([
        'objects', 'owners', 'contacts', 'prices', 'services', 'geography',
        'packages', 'payments', 'banners', 'news', 'promotions', 'statistics',
        'action_journal',
    ]);
});

it('resolves every declared kind to an existing Eloquent model', function (): void {
    foreach (TransferableRegistry::all() as $key => $transferable) {
        expect(class_exists($transferable->model))
            ->toBeTrue("Kind [{$key}] declares model [{$transferable->model}], which does not exist.")
            ->and(is_subclass_of($transferable->model, Model::class))
            ->toBeTrue("Kind [{$key}]'s declared model [{$transferable->model}] is not an Eloquent model.");
    }
});

it('declares only columns that exist on the model\'s own table', function (): void {
    foreach (TransferableRegistry::all() as $key => $transferable) {
        /** @var Model $instance */
        $instance = new $transferable->model;
        $table = $instance->getTable();

        foreach ($transferable->columnNames() as $column) {
            expect(Schema::hasColumn($table, $column))
                ->toBeTrue("Kind [{$key}] declares column [{$column}], absent from table [{$table}].");
        }
    }
});

it('declares a non-empty label and at least one export format per kind', function (): void {
    foreach (TransferableRegistry::all() as $key => $transferable) {
        expect($transferable->label)->not->toBe('')
            ->and($transferable->formats)->not->toBeEmpty("Kind [{$key}] declares no export format.");
    }
});

it('flags the payments kind as financial and the owners/contacts kinds as personal data', function (): void {
    expect(TransferableRegistry::get('payments')->requiresFinancialPermission)->toBeTrue()
        ->and(TransferableRegistry::get('owners')->requiresPersonalDataPermission)->toBeTrue()
        ->and(TransferableRegistry::get('contacts')->requiresPersonalDataPermission)->toBeTrue();
});

it('rejects an unknown data-type key', function (): void {
    expect(fn () => TransferableRegistry::get('unknown-kind'))->toThrow(InvalidArgumentException::class);
});

/*
|--------------------------------------------------------------------------
| Registry Containment
|--------------------------------------------------------------------------
|
| A sweep, not a per-exporter assertion — the same construction the
| indexation invariant used against the public route registry: every
| Exporter class app/Filament declares is discovered by path rather than
| named by hand, so an exporter added later without a matching registry
| entry fails this test instead of shipping a column the import side has
| never heard of. Dotted names (`package.name`, `user.name`) address a
| related model's own attribute for display and are not one of the
| entity's own columns, so they are outside what the registry declares.
|
*/

it('never lets an Exporter name a column its own kind does not declare', function (): void {
    $registryByModel = [];
    foreach (TransferableRegistry::all() as $transferable) {
        $registryByModel[$transferable->model] = $transferable;
    }

    $violations = [];

    $files = [];
    $directory = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path('app/Filament')));
    foreach ($directory as $fileInfo) {
        if ($fileInfo->isFile() && str_ends_with($fileInfo->getFilename(), 'Exporter.php')) {
            $files[] = $fileInfo->getPathname();
        }
    }

    foreach ($files as $file) {
        $relative = Str::of($file)->after(base_path('app').DIRECTORY_SEPARATOR)->replace(['/', '\\'], '\\')->beforeLast('.php');
        $fqcn = 'App\\'.$relative;

        expect(class_exists($fqcn))->toBeTrue("Exporter file [{$file}] does not resolve to class [{$fqcn}].");
        expect(is_subclass_of($fqcn, Exporter::class))->toBeTrue("[{$fqcn}] does not extend Filament's Exporter.");

        /** @var class-string<Exporter> $fqcn */
        $model = $fqcn::getModel();

        expect(array_key_exists($model, $registryByModel))
            ->toBeTrue("[{$fqcn}] exports [{$model}], which no transferable kind declares.");

        /** @var Transferable $transferable */
        $transferable = $registryByModel[$model];
        $declared = $transferable->columnNames();

        /** @var list<ExportColumn> $columns */
        $columns = $fqcn::getColumns();

        foreach ($columns as $column) {
            $name = $column->getName();

            if (str_contains($name, '.')) {
                continue; // Relation-display column — not this entity's own.
            }

            if (! in_array($name, $declared, true)) {
                $violations[] = "{$fqcn} names column [{$name}], absent from the [{$transferable->key}] registry entry.";
            }
        }
    }

    expect($violations)->toBe([]);
});
