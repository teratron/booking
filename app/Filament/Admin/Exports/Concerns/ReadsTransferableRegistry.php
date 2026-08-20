<?php

declare(strict_types=1);

namespace App\Filament\Admin\Exports\Concerns;

use App\Filament\Admin\Exports\JsonExportFormat;
use App\Filament\Admin\Imports\ObjectImporter;
use App\Jobs\ExecuteObjectBulkActionJob;
use App\Models\User;
use App\Services\Audit\AuditJournal;
use App\Services\DataTransfer\TransferableColumn;
use App\Services\DataTransfer\TransferableRegistry;
use Filament\Actions\Exports\Enums\Contracts\ExportFormat as ExportFormatContract;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Models\Export;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;

/**
 * Reads a Filament exporter's model, column set, and download formats from
 * the transferable data-type registry rather than a second, independently
 * hand-maintained inventory — the mechanism that keeps a column added to
 * the registry from silently never reaching an export the registry already
 * claims to drive.
 *
 * Not named `*Exporter` on purpose: the registry containment sweep
 * discovers every `*Exporter.php` file under `app/Filament` by path and
 * calls `getModel()`/`getColumns()` on it directly, which would fail on an
 * abstract `transferableKey()`. A trait, consumed only by concrete
 * exporters that implement it, keeps this base out of that sweep.
 */
trait ReadsTransferableRegistry
{
    /**
     * The registry key this exporter reads. Concrete per-kind exporters
     * return a fixed string; an exporter converted from a pre-existing,
     * hand-built column list may still override {@see getColumns()} below
     * while relying on this only for {@see getModel()} and format/
     * notification defaults.
     */
    abstract public static function transferableKey(): string;

    public static function getModel(): string
    {
        return TransferableRegistry::get(static::transferableKey())->model;
    }

    /**
     * Declared explicitly rather than left to the connection's implicit
     * default — see config/horizon.php's own "bulk" supervisor, shared
     * with {@see ObjectImporter} and
     * {@see ExecuteObjectBulkActionJob}, all long-running,
     * multi-row work.
     */
    public function getJobQueue(): string
    {
        return 'bulk';
    }

    /** @return list<ExportColumn> */
    public static function getColumns(): array
    {
        return array_map(
            static fn (TransferableColumn $column): ExportColumn => ExportColumn::make($column->name)->label($column->label),
            TransferableRegistry::get(static::transferableKey())->columns,
        );
    }

    /**
     * The single choke point Filament's own export pipeline reads a
     * declared column set through — both the column-selection modal and
     * the column map actually written to the artefact are built from this,
     * never from {@see getColumns()} directly (see `CanExportRecords`).
     * Narrowing here, rather than in `getColumns()` itself, keeps every
     * exporter's own hand-written column list (including the three
     * converted from a pre-existing implementation) intact for callers
     * that legitimately want the full declaration, while guaranteeing a
     * personal-data or financial column the acting staff member's
     * permissions do not cover never reaches an actual export — the
     * column is omitted outright, not merely hidden from the form, so a
     * request cannot smuggle it back in by supplying its own column map.
     *
     * @return list<ExportColumn>
     */
    public static function getVisibleColumns(): array
    {
        return array_values(array_filter(
            static::narrowColumnsForActor(static::getColumns()),
            fn (ExportColumn $column): bool => $column->isVisible(),
        ));
    }

    /**
     * Drops any column the transferable registry flags as personal data or
     * financial when the current staff member holds only the entity's
     * general `.export` permission — the specification's own distinction
     * between "may move this resource's rows" and "may move this
     * resource's sensitive columns". Never a whole-export refusal: an
     * actor missing both standalone permissions still exports every
     * non-sensitive column of a kind that happens to carry some.
     *
     * @param  list<ExportColumn>  $columns
     * @return list<ExportColumn>
     */
    protected static function narrowColumnsForActor(array $columns): array
    {
        $transferable = TransferableRegistry::get(static::transferableKey());
        $actor = static::currentActor();

        $restricted = [];

        if (! ($actor?->can('personal_data_access') ?? false)) {
            array_push($restricted, ...$transferable->personalDataColumnNames());
        }

        if (! ($actor?->can('financial_access') ?? false)) {
            array_push($restricted, ...$transferable->financialColumnNames());
        }

        if ($restricted === []) {
            return $columns;
        }

        return array_values(array_filter(
            $columns,
            fn (ExportColumn $column): bool => ! in_array($column->getName(), $restricted, true),
        ));
    }

    private static function currentActor(): ?User
    {
        $user = Filament::auth()->user();

        return $user instanceof User ? $user : null;
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return trans_choice(
            'panel.data_transfer.export.notifications.completed',
            $export->successful_rows,
            ['count' => number_format($export->successful_rows)],
        );
    }

    /**
     * Writes exactly one action-journal entry per completed export, once
     * Filament's own queued pipeline has finished writing it — this hook
     * already runs precisely once per export (`ExportCompletion::handle()`
     * calls it while building the completion notification), so it doubles
     * as the journalling seam without a second lifecycle to keep in sync.
     * The notification itself is returned unmodified.
     */
    public static function modifyCompletedNotification(Notification $notification, Export $export): Notification
    {
        static::journalExport($export);

        return $notification;
    }

    /**
     * The filter set travels here via the export's own transient options
     * bag: the triggering `ExportAction` captures the Livewire table's
     * active filters into `->options()`, Filament merges that into the
     * payload every job in the export chain receives, and `Export::options()`
     * — never persisted as a database column, only carried through the job
     * chain in memory — reconstructs it here from that same payload.
     */
    protected static function journalExport(Export $export): void
    {
        app(AuditJournal::class)->record(
            event: 'data_exported',
            target: $export,
            newValues: [
                'kind' => static::transferableKey(),
                'count' => $export->successful_rows,
                'filters' => $export->getOptions()['filters'] ?? [],
            ],
            actor: $export->user,
            tags: ['export', static::transferableKey()],
        );
    }

    /**
     * Filament's own CSV/XLSX pair, plus JSON. The queued export pipeline
     * always writes CSV chunk files regardless of which formats are
     * declared here — XLSX is itself only a download-time conversion of
     * those same chunks (see `XlsxDownloader`) — and JSON follows the
     * identical pattern via {@see JsonExportFormat}.
     *
     * @return list<ExportFormatContract>
     */
    public function getFormats(): array
    {
        return [ExportFormat::Csv, ExportFormat::Xlsx, JsonExportFormat::Json];
    }

    /**
     * Appends any registry-declared column not already present in an
     * explicit, hand-built column list — the closure mechanism for a
     * pre-existing exporter that keeps custom labels, relation-display
     * columns, and formatState callbacks exactly as they were, while still
     * picking up a column the registry declares that the hand-built list
     * does not yet name.
     *
     * `$relationSubstitutes` names, for a registry column already covered
     * by a differently-named display column (e.g. a dotted relation
     * attribute showing a related record's name instead of its own raw
     * foreign key), which already-present column covers it — so that
     * column is not duplicated as a second, raw-id column.
     *
     * @param  list<ExportColumn>  $columns
     * @param  array<string, string>  $relationSubstitutes
     * @return list<ExportColumn>
     */
    protected static function appendMissingRegistryColumns(array $columns, array $relationSubstitutes = []): array
    {
        $present = array_map(static fn (ExportColumn $column): string => $column->getName(), $columns);

        foreach (TransferableRegistry::get(static::transferableKey())->columns as $column) {
            if (in_array($column->name, $present, true)) {
                continue;
            }

            if (isset($relationSubstitutes[$column->name]) && in_array($relationSubstitutes[$column->name], $present, true)) {
                continue;
            }

            $columns[] = ExportColumn::make($column->name)->label($column->label);
        }

        return $columns;
    }
}
