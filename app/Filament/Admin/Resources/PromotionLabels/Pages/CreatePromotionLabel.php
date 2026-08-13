<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PromotionLabels\Pages;

use App\Filament\Admin\Resources\PromotionLabels\PromotionLabelResource;
use App\Models\PromotionLabel;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Override;

class CreatePromotionLabel extends CreateRecord
{
    protected static string $resource = PromotionLabelResource::class;

    /** @var array<string, array<string, mixed>> */
    private array $pendingTranslations = [];

    /** @return array<string, mixed> */
    #[Override]
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->pendingTranslations = $data['translations'] ?? [];
        unset($data['translations']);

        return $data;
    }

    #[Override]
    protected function handleRecordCreation(array $data): Model
    {
        /** @var PromotionLabel $record */
        $record = parent::handleRecordCreation($data);

        foreach ($this->pendingTranslations as $locale => $fields) {
            $fields = array_filter($fields, static fn ($value): bool => $value !== null && $value !== '');

            if ($fields === []) {
                continue;
            }

            $record->translations()->create(['locale' => $locale, ...$fields]);
        }

        return $record;
    }
}
