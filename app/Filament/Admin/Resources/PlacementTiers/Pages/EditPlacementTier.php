<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PlacementTiers\Pages;

use App\Filament\Admin\Resources\PlacementTiers\PlacementTierResource;
use App\Models\PlacementTier;
use App\Models\PlacementTierTranslation;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Override;

/**
 * No delete action: the four ranks are structural, so this page only ever
 * edits presentation, never removes a rank.
 */
class EditPlacementTier extends EditRecord
{
    protected static string $resource = PlacementTierResource::class;

    /** @var array<string, array<string, mixed>> */
    private array $pendingTranslations = [];

    /** @return array<string, mixed> */
    #[Override]
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->currentPlacementTier();

        /** @var PlacementTierTranslation $translation */
        foreach ($record->translations as $translation) {
            $data['translations'][$translation->locale] = $translation->only(['label', 'badge_text']);
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
        /** @var PlacementTier $record */
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

    private function currentPlacementTier(): PlacementTier
    {
        /** @var PlacementTier $record */
        $record = $this->getRecord();

        return $record;
    }
}
