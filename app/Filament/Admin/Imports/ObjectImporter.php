<?php

declare(strict_types=1);

namespace App\Filament\Admin\Imports;

use App\Models\Object_;
use App\Services\DataTransfer\TransferableColumn;
use App\Services\DataTransfer\TransferableRegistry;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Override;

/**
 * Translates the "objects" kind's data-transfer registry declaration into
 * Filament's own per-row import machinery, so the objects import pipeline's
 * validation, casting, and record resolution run through one real, already
 * queue-capable pipeline rather than a hand-rolled parallel one.
 *
 * Runs in two modes, selected by the `dryRun` option on the importer
 * instance: the validation/preview pass constructs one importer per row with
 * `dryRun: true`, so {@see saveRecord()} never reaches the database while
 * every other step — column mapping, casting, foreign-key and enum
 * validation, record resolution — still runs for real. Confirming the
 * import constructs the same class without that option, and the identical
 * per-row logic writes, queued through Filament's own chunked import job.
 * A row that previews clean is therefore guaranteed to write clean: both
 * passes are the same code, not two definitions of "valid" that could
 * drift apart.
 */
final class ObjectImporter extends Importer
{
    protected static ?string $model = Object_::class;

    /** @return array<ImportColumn> */
    #[Override]
    public static function getColumns(): array
    {
        return array_map(
            self::buildColumn(...),
            TransferableRegistry::get('objects')->columns,
        );
    }

    /**
     * Matches on the "ulid" column first — the registry's own public
     * identifier for an object — falling back to the raw "id" only when no
     * ulid was supplied. `withTrashed()` is deliberate: it exists to catch a
     * ulid collision against an archived object before the unique
     * constraint would, not to resurrect one — nothing here calls
     * `restore()`, so a match on a soft-deleted row stays soft-deleted.
     * `withUnmoderated()` is required for the same reason it is on every
     * other staff-facing query against this model: the default moderation
     * scope would otherwise hide a row still awaiting its first approval.
     */
    #[Override]
    public function resolveRecord(): Model
    {
        $ulid = $this->data['ulid'] ?? null;

        if (filled($ulid)) {
            return Object_::query()->withUnmoderated()->withTrashed()->where('ulid', $ulid)->first()
                ?? new Object_(['ulid' => $ulid]);
        }

        $id = $this->data['id'] ?? null;

        if (filled($id)) {
            return Object_::query()->withUnmoderated()->withTrashed()->where('id', $id)->first() ?? new Object_;
        }

        return new Object_;
    }

    #[Override]
    public function saveRecord(): void
    {
        if ($this->getOptions()['dryRun'] ?? false) {
            return;
        }

        parent::saveRecord();
    }

    /**
     * A brand-new row needs both a public identifier and a publication
     * state before it can be saved — `ulid` has no database default (unlike
     * `status`, which merely mirrors the same default here for clarity) —
     * and an import file is not required to carry either column.
     */
    protected function beforeCreate(): void
    {
        $record = $this->record;

        if (! $record instanceof Object_) {
            return;
        }

        if (blank($record->ulid)) {
            $record->ulid = (string) Str::ulid();
        }

        if (blank($record->status)) {
            $record->status = 'draft';
        }
    }

    #[Override]
    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = trans_choice(
            'panel.data_transfer.import.notifications.completed_body',
            $import->successful_rows,
            ['count' => $import->successful_rows],
        );

        $failedCount = $import->getFailedRowsCount();

        if ($failedCount < 1) {
            return $body;
        }

        return "{$body} ".trans_choice(
            'panel.data_transfer.import.notifications.completed_failures',
            $failedCount,
            ['count' => $failedCount],
        );
    }

    private static function buildColumn(TransferableColumn $column): ImportColumn
    {
        $import = ImportColumn::make($column->name)->label($column->label);

        return match ($column->name) {
            'owner_id' => $import->integer()->rules(['required', 'integer', 'exists:users,id']),
            'object_type_id' => $import->integer()->rules(['required', 'integer', 'exists:object_types,id']),
            'territory_id' => $import->integer()->rules(['required', 'integer', 'exists:territories,id']),
            'country_id' => $import->integer()->rules(['required', 'integer', 'exists:countries,id']),
            // Blank state is ignored on status/availability_status so a
            // partial update row never wipes the existing value back to
            // null — a blank cell means "leave this field alone", not
            // "clear it", which matches how the mapping step treats every
            // other optional column too.
            'status' => $import->ignoreBlankState()->rules(['nullable', 'string', 'in:draft,published,hidden,archived']),
            'availability_status' => $import->ignoreBlankState()->rules(['nullable', 'string', 'in:available,unavailable,unspecified']),
            'id' => $import->integer()->ignoreBlankState()->rules(['nullable', 'integer']),
            'ulid' => $import->ignoreBlankState()->rules(['nullable', 'string', 'size:26']),
            default => self::applyGenericType($import, $column),
        };
    }

    private static function applyGenericType(ImportColumn $column, TransferableColumn $definition): ImportColumn
    {
        return match ($definition->type) {
            'integer' => $column->integer()->ignoreBlankState()->rules(['nullable', 'integer']),
            'decimal' => $column->numeric()->ignoreBlankState()->rules(['nullable', 'numeric']),
            'boolean' => $column->boolean()->ignoreBlankState()->rules(['nullable', 'boolean']),
            'date' => $column->ignoreBlankState()
                ->castStateUsing(static fn (mixed $state): ?string => filled($state) ? Carbon::parse((string) $state)->toDateString() : null)
                ->rules(['nullable', 'date']),
            'datetime' => $column->ignoreBlankState()
                ->castStateUsing(static fn (mixed $state): ?string => filled($state) ? (string) Carbon::parse((string) $state) : null)
                ->rules(['nullable', 'date']),
            default => $column->ignoreBlankState()->rules(['nullable', 'string']),
        };
    }
}
