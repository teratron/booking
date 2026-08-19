<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Models\User;
use App\Services\DataTransfer\DuplicateCandidate;
use App\Services\DataTransfer\DuplicateSignal;
use App\Services\DataTransfer\ImportKindRegistry;
use App\Services\DataTransfer\ImportPipelineService;
use App\Services\DataTransfer\ImportRowDuplicate;
use App\Services\DataTransfer\ImportRowError;
use App\Services\DataTransfer\SpreadsheetParser;
use App\Services\DataTransfer\TransferableColumn;
use App\Services\DataTransfer\TransferableRegistry;
use BackedEnum;
use Filament\Actions\Imports\Models\FailedImportRow;
use Filament\Actions\Imports\Models\Import;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use UnitEnum;

/**
 * The import pipeline the specification states in one sentence: upload a
 * file, choose the data type, map its columns, validate, list the errors,
 * preview what would change, confirm, and produce a report — as five
 * distinct steps rather than one form submission, because nothing may be
 * written until the operator has seen the error list and confirmed the
 * preview.
 *
 * Parsed rows never sit in this component's own Livewire state between
 * steps — only a small cache token does — so the payload a large import
 * pushes back and forth on every step transition stays constant-sized
 * regardless of how many rows the uploaded file carries.
 */
class DataImport extends Page
{
    use WithFileUploads;

    protected string $view = 'filament.admin.pages.data-import';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static string|UnitEnum|null $navigationGroup = 'system';

    public ?string $kind = null;

    public ?TemporaryUploadedFile $file = null;

    /** @var array<string, string|null> */
    public array $columnMap = [];

    /** @var list<string> */
    public array $headers = [];

    public ?string $rowsToken = null;

    public ?int $totalRows = null;

    public ?string $originalFileName = null;

    public ?string $storedFilePath = null;

    public string $step = 'upload';

    /** @var array{total: int, create: int, update: int, errors: int}|null */
    public ?array $previewSummary = null;

    /** @var list<array{row: int, messages: list<string>}> */
    public array $previewErrors = [];

    /** @var list<array{row: int, candidates: list<array{label: string, signals: list<string>}>}> */
    public array $previewDuplicates = [];

    public ?int $importId = null;

    public ?int $viewingImportId = null;

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->can('object.create');
    }

    public static function getNavigationLabel(): string
    {
        return __('panel.data_transfer.import.title');
    }

    public function getTitle(): string
    {
        return __('panel.data_transfer.import.title');
    }

    /** @return array<string, string> */
    public function kindOptions(): array
    {
        $options = [];

        foreach (array_keys(ImportKindRegistry::all()) as $key) {
            $options[$key] = TransferableRegistry::get($key)->label;
        }

        return $options;
    }

    /** @return list<TransferableColumn> */
    public function targetColumns(): array
    {
        if ($this->kind === null || ! ImportKindRegistry::isSupported($this->kind)) {
            return [];
        }

        return TransferableRegistry::get($this->kind)->columns;
    }

    /**
     * Stage 1 → 2. Stores the upload permanently (so the confirm stage can
     * still reach the exact file that was validated), parses it into rows,
     * and holds those rows in cache under a short-lived token rather than as
     * component state.
     */
    public function parseFile(): void
    {
        $this->validate([
            'kind' => ['required', Rule::in(array_keys(ImportKindRegistry::all()))],
            'file' => ['required', 'file'],
        ]);

        if (! $this->file instanceof TemporaryUploadedFile) {
            return;
        }

        $extension = strtolower($this->file->getClientOriginalExtension() ?: pathinfo((string) $this->file->getClientOriginalName(), PATHINFO_EXTENSION));

        if (! in_array($extension, ['xlsx', 'csv'], true)) {
            $this->addError('file', __('panel.data_transfer.import.errors.unsupported_file'));

            return;
        }

        $relativePath = $this->file->storeAs('imports', Str::uuid()->toString().'.'.$extension, 'local');

        if ($relativePath === false) {
            $this->addError('file', __('panel.data_transfer.import.errors.unsupported_file'));

            return;
        }

        $absolutePath = Storage::disk('local')->path($relativePath);

        $parsed = app(SpreadsheetParser::class)->parse($absolutePath, $extension);

        if ($parsed->rows === []) {
            Notification::make()->title(__('panel.data_transfer.import.notifications.empty_file'))->danger()->send();

            return;
        }

        $this->originalFileName = $this->file->getClientOriginalName();
        $this->storedFilePath = $relativePath;
        $this->headers = $parsed->headers;
        $this->totalRows = count($parsed->rows);

        $token = (string) Str::uuid();
        Cache::put($this->cacheKey($token), $parsed->rows, now()->addHour());
        $this->rowsToken = $token;

        $this->columnMap = $this->guessColumnMap($parsed->headers);
        $this->step = 'mapping';
    }

    /** Stage 2 → 3. Nothing here writes to the target table. */
    public function runPreview(): void
    {
        $rows = $this->cachedRows();

        if ($rows === null || $this->kind === null) {
            $this->expireBackToUpload();

            return;
        }

        $result = app(ImportPipelineService::class)->preview($this->kind, $rows, $this->columnMap);

        $this->previewSummary = [
            'total' => $result->totalRows,
            'create' => $result->createCount,
            'update' => $result->updateCount,
            'errors' => $result->errorCount(),
            'duplicates' => $result->duplicateCount(),
        ];

        $this->previewErrors = array_map(
            static fn (ImportRowError $error): array => ['row' => $error->row, 'messages' => $error->messages],
            $result->errors,
        );

        $this->previewDuplicates = array_map(
            static fn (ImportRowDuplicate $duplicate): array => [
                'row' => $duplicate->row,
                'candidates' => array_map(
                    static fn (DuplicateCandidate $candidate): array => [
                        'label' => $candidate->label,
                        'signals' => array_map(static fn (DuplicateSignal $signal): string => $signal->detail, $candidate->signals),
                    ],
                    $duplicate->candidates,
                ),
            ],
            $result->duplicates,
        );

        $this->step = 'preview';
    }

    /** Stage 3 → 4. Queues the real write and produces the durable report row. */
    public function confirmImport(): void
    {
        $rows = $this->cachedRows();

        if ($rows === null || $this->kind === null || $this->storedFilePath === null) {
            $this->expireBackToUpload();

            return;
        }

        $import = app(ImportPipelineService::class)->confirm(
            $this->kind,
            $rows,
            $this->columnMap,
            $this->actor(),
            $this->originalFileName ?? $this->storedFilePath,
            $this->storedFilePath,
        );

        if ($this->rowsToken !== null) {
            Cache::forget($this->cacheKey($this->rowsToken));
        }

        $this->importId = $import->id;
        $this->step = 'done';

        Notification::make()
            ->title(trans_choice('panel.data_transfer.import.notifications.queued', $import->total_rows, ['count' => $import->total_rows]))
            ->success()
            ->send();
    }

    public function startOver(): void
    {
        if ($this->rowsToken !== null) {
            Cache::forget($this->cacheKey($this->rowsToken));
        }

        $this->reset([
            'kind', 'file', 'columnMap', 'headers', 'rowsToken', 'totalRows',
            'originalFileName', 'storedFilePath', 'previewSummary', 'previewErrors', 'previewDuplicates', 'importId',
        ]);
        $this->step = 'upload';
    }

    /** @return Collection<int, Import> */
    public function recentImports(): Collection
    {
        return Import::query()->latest('id')->limit(20)->get();
    }

    /**
     * `Import::completed_at` casts to a raw Unix timestamp (`timestamp`),
     * not a Carbon instance — Filament's own model declares it as one in
     * its docblock, but the cast it actually applies produces an `int`, so
     * this reads the raw attribute rather than trusting that annotation.
     */
    public function completedAtLabel(Import $import): string
    {
        /** @var int|null $completedAt */
        $completedAt = $import->getAttribute('completed_at');

        if ($completedAt === null) {
            return __('panel.data_transfer.import.report.pending');
        }

        return Carbon::createFromTimestamp($completedAt)->diffForHumans();
    }

    public function toggleFailedRows(int $importId): void
    {
        $this->viewingImportId = $this->viewingImportId === $importId ? null : $importId;
    }

    /** @return Collection<int, FailedImportRow> */
    public function viewingFailedRows(): Collection
    {
        return FailedImportRow::query()
            ->where('import_id', $this->viewingImportId ?? 0)
            ->latest('id')
            ->limit(50)
            ->get();
    }

    /** @return list<array<string, mixed>>|null */
    private function cachedRows(): ?array
    {
        if ($this->rowsToken === null) {
            return null;
        }

        /** @var list<array<string, mixed>>|null $rows */
        $rows = Cache::get($this->cacheKey($this->rowsToken));

        return $rows;
    }

    private function expireBackToUpload(): void
    {
        Notification::make()->title(__('panel.data_transfer.import.notifications.expired'))->danger()->send();
        $this->step = 'upload';
    }

    private function cacheKey(string $token): string
    {
        return "data-import-rows:{$token}";
    }

    /**
     * @param  list<string>  $headers
     * @return array<string, string|null>
     */
    private function guessColumnMap(array $headers): array
    {
        if ($this->kind === null) {
            return [];
        }

        $normalizedHeaders = [];

        foreach ($headers as $header) {
            $normalizedHeaders[Str::of($header)->lower()->snake()->toString()] = $header;
        }

        $map = [];

        foreach (TransferableRegistry::get($this->kind)->columns as $column) {
            $needle = Str::of($column->name)->lower()->snake()->toString();
            $map[$column->name] = $normalizedHeaders[$needle] ?? null;
        }

        return $map;
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = Filament::auth()->user();

        return $user;
    }
}
