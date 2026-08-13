<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\FinancialRecords\Pages;

use App\Filament\Admin\Resources\FinancialRecords\FinancialRecordResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Override;

class EditFinancialRecord extends EditRecord
{
    protected static string $resource = FinancialRecordResource::class;

    /** @return array<string, mixed> */
    #[Override]
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['subject_kind'] = ($data['object_id'] ?? null) !== null ? 'object' : 'banner';

        return $data;
    }

    /** @return array<string, mixed> */
    #[Override]
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (($data['object_id'] ?? null) !== null) {
            $data['banner_id'] = null;
        } else {
            $data['object_id'] = null;
        }

        return $data;
    }

    /** @return array<DeleteAction> */
    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
