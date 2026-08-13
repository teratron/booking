<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BannerSlots\Pages;

use App\Filament\Admin\Resources\BannerSlots\BannerSlotResource;
use App\Models\BannerSlot;
use App\Models\BannerSlotTranslation;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Override;

class EditBannerSlot extends EditRecord
{
    protected static string $resource = BannerSlotResource::class;

    /** @var array<string, array<string, mixed>> */
    private array $pendingTranslations = [];

    /** @return array<string, mixed> */
    #[Override]
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->currentBannerSlot();

        /** @var BannerSlotTranslation $translation */
        foreach ($record->translations as $translation) {
            $data['translations'][$translation->locale] = $translation->only(['name']);
        }

        return $data;
    }

    /** @return array<string, mixed> */
    #[Override]
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->pendingTranslations = $data['translations'] ?? [];
        unset($data['translations']);

        return $data;
    }

    #[Override]
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var BannerSlot $record */
        $record->update($data);

        foreach ($this->pendingTranslations as $locale => $fields) {
            $fields = array_filter($fields, static fn ($value): bool => $value !== null && $value !== '');

            if ($fields === []) {
                continue;
            }

            $record->translations()->updateOrCreate(['locale' => $locale], $fields);
        }

        return $record;
    }

    /** @return array<DeleteAction> */
    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    private function currentBannerSlot(): BannerSlot
    {
        /** @var BannerSlot $record */
        $record = $this->getRecord();

        return $record;
    }
}
