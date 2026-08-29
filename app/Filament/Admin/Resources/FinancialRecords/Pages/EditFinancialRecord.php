<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\FinancialRecords\Pages;

use App\Filament\Admin\Resources\FinancialRecords\FinancialRecordResource;
use App\Models\FinancialRecord;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Override;

/**
 * `financial_records` carries its own `enforce_append_only()` trigger —
 * the ledger's integrity depends on a row never changing once written, so
 * a correction is a delete followed by a new, correct record, never an
 * in-place edit. The shared form still needs to render here to show a
 * record's own values, but it must never actually submit a change: the
 * trigger would refuse the resulting UPDATE outright, surfacing as a raw,
 * uncaught database error rather than a page an administrator can use.
 *
 * `form()->disabled()` alone is a UI-level courtesy, not a guarantee — it
 * stops interactive typing but does not stop a submitted request (or a
 * test driving the component directly) from carrying changed values
 * through to save(). `handleRecordUpdate()` below is the actual guarantee:
 * it never calls `$record->update()` at all, so no path through this page
 * can reach the trigger, regardless of what the submitted data claims.
 */
class EditFinancialRecord extends EditRecord
{
    protected static string $resource = FinancialRecordResource::class;

    #[Override]
    public function form(Schema $schema): Schema
    {
        return parent::form($schema)->disabled();
    }

    /** @param  array<string, mixed>  $data */
    #[Override]
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var FinancialRecord $record */
        return $record;
    }

    /** @return array<string, mixed> */
    #[Override]
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['subject_kind'] = ($data['object_id'] ?? null) !== null ? 'object' : 'banner';

        return $data;
    }

    /** @return array<DeleteAction> */
    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    /**
     * No Save action: handleRecordUpdate() above already makes one a no-op,
     * but a button that appears to do something while silently changing
     * nothing is its own kind of confusing. Deleting the record (a genuine
     * correction path) stays available above; Cancel is Filament's own
     * built-in action, not a bespoke one.
     *
     * @return array<Action>
     */
    #[Override]
    protected function getFormActions(): array
    {
        return [$this->getCancelFormAction()];
    }
}
