<?php

declare(strict_types=1);

namespace App\Services\DataTransfer;

use App\Models\User;
use Filament\Actions\ImportAction;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Jobs\ImportCsv;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Facades\Bus;
use Illuminate\Validation\ValidationException;

/**
 * The two-phase import pipeline the back office's import screen drives: a
 * dry-run validation pass that writes nothing, and a confirmed pass that
 * persists a durable report row and queues the real write.
 *
 * Both phases run the identical per-row {@see Importer}
 * logic {@see ImportKindRegistry} resolves for the chosen kind — the same
 * class, with one option flipped — so a row that previews clean is
 * guaranteed to write clean rather than risking two hand-maintained
 * definitions of "valid" drifting apart.
 */
final class ImportPipelineService
{
    public function __construct(
        private readonly DuplicateDetectionService $duplicateDetection,
    ) {}

    /**
     * Validates every row against the target kind's declared columns
     * without writing a single one. Every check a real import would run —
     * required fields, foreign-key existence, enum membership — executes
     * for real against the database (read-only: existence checks are
     * `SELECT`s), but the importer's own `saveRecord()` is short-circuited
     * by the `dryRun` option before it reaches the target table.
     *
     * A row that would create a new object (as opposed to updating one
     * already matched by ulid or id) is additionally checked against the
     * existing catalog for a plausible duplicate — an update row names its
     * own target explicitly and is never ambiguous, so only creates are
     * worth comparing. Duplicate candidates are surfaced, never acted on:
     * this pass writes nothing regardless of what it finds.
     *
     * @param  list<array<string, mixed>>  $rows  raw rows keyed by the uploaded file's own headers
     * @param  array<string, string|null>  $columnMap  registry column name => uploaded header name, or null when unmapped
     */
    public function preview(string $kind, array $rows, array $columnMap): ImportPreviewResult
    {
        $importerClass = ImportKindRegistry::importerFor($kind);
        $placeholderImport = new Import;

        $created = 0;
        $updated = 0;
        $errors = [];
        $duplicates = [];

        foreach ($rows as $index => $row) {
            $importer = new $importerClass($placeholderImport, $columnMap, ['dryRun' => true]);

            try {
                $importer($row);
            } catch (ValidationException $exception) {
                $messages = array_values(array_map('strval', collect($exception->errors())->flatten()->all()));
                $errors[] = new ImportRowError($index + 1, $messages);

                continue;
            }

            $record = $importer->getRecord();

            if ($record?->exists) {
                $updated++;

                continue;
            }

            $created++;

            // DuplicateDetectionService compares against the objects
            // catalog specifically — safe unconditionally today because
            // ImportKindRegistry itself supports only the "objects" kind;
            // wiring a second kind here needs its own kind check the same
            // way that registry already draws its own honest boundary.
            $candidates = $this->duplicateDetection->detect(NewObjectSignals::fromRow($row, $columnMap));

            if ($candidates !== []) {
                $duplicates[] = new ImportRowDuplicate($index + 1, $candidates);
            }
        }

        return new ImportPreviewResult(
            totalRows: count($rows),
            createCount: $created,
            updateCount: $updated,
            errors: $errors,
            duplicates: $duplicates,
        );
    }

    /**
     * Persists the durable `imports` bookkeeping row — the report an
     * operator reads after the session that started it has ended — and
     * queues the real write, chunked through Filament's own
     * {@see ImportCsv} job. That job is the identical native machinery
     * {@see ImportAction} itself dispatches, reused here
     * rather than rebuilt: the preview pass already proved these rows
     * validate against the very same per-row logic this job runs.
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, string|null>  $columnMap
     */
    public function confirm(
        string $kind,
        array $rows,
        array $columnMap,
        User $actor,
        string $originalFileName,
        string $storedRelativePath,
    ): Import {
        $importerClass = ImportKindRegistry::importerFor($kind);

        $import = new Import;
        $import->user()->associate($actor);
        $import->file_name = $originalFileName;
        $import->file_path = $storedRelativePath;
        $import->importer = $importerClass;
        $import->total_rows = count($rows);
        $import->save();

        // Not sent into the job payload: the loaded relation can carry
        // attributes the queue serializer chokes on, and the job re-resolves
        // it itself from `user_id` — the same precaution Filament's own
        // ImportAction takes before dispatching.
        $import->unsetRelation('user');

        Bus::batch([
            app(ImportCsv::class, [
                'import' => $import,
                'rows' => $rows,
                'columnMap' => $columnMap,
                'options' => [],
            ]),
        ])
            ->allowFailures()
            ->finally(function () use ($import): void {
                $import->touch('completed_at');
            })
            ->dispatch();

        return $import->fresh() ?? $import;
    }
}
