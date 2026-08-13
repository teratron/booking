<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\FinancialRecords\Pages;

use App\Filament\Admin\Resources\FinancialRecords\FinancialRecordResource;
use Filament\Resources\Pages\CreateRecord;
use Override;

class CreateFinancialRecord extends CreateRecord
{
    protected static string $resource = FinancialRecordResource::class;

    /**
     * The table's own check constraint requires exactly one of
     * object_id/banner_id — the form's `subject_kind` toggle is a UI
     * convenience, not dehydrated itself, so whichever pairing the
     * administrator did not choose is nulled explicitly here rather than
     * trusted to have been omitted by the form alone.
     *
     * @return array<string, mixed>
     */
    #[Override]
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (($data['object_id'] ?? null) !== null) {
            $data['banner_id'] = null;
        } else {
            $data['object_id'] = null;
        }

        return $data;
    }
}
