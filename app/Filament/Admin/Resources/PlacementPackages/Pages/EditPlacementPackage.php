<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PlacementPackages\Pages;

use App\Filament\Admin\Resources\PlacementPackages\PlacementPackageResource;
use App\Models\PlacementPackage;
use App\Models\PlacementPackageTranslation;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Override;

class EditPlacementPackage extends EditRecord
{
    protected static string $resource = PlacementPackageResource::class;

    /** @var array<string, array<string, mixed>> */
    private array $pendingTranslations = [];

    /** @return array<string, mixed> */
    #[Override]
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->currentPlacementPackage();

        /** @var PlacementPackageTranslation $translation */
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
        /** @var PlacementPackage $record */
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

    private function currentPlacementPackage(): PlacementPackage
    {
        /** @var PlacementPackage $record */
        $record = $this->getRecord();

        return $record;
    }
}
