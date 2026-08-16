<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ObjectTypes\Pages;

use App\Filament\Admin\Resources\ObjectTypes\ObjectTypeResource;
use App\Models\ObjectType;
use App\Models\ObjectTypeTranslation;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Override;

class EditObjectType extends EditRecord
{
    protected static string $resource = ObjectTypeResource::class;

    /** @var array<string, array<string, mixed>> */
    private array $pendingTranslations = [];

    /** @return array<string, mixed> */
    #[Override]
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->currentObjectType();

        /** @var ObjectTypeTranslation $translation */
        foreach ($record->translations as $translation) {
            $data['translations'][$translation->locale] = $translation->only([
                'name', 'slug', 'seo_title', 'seo_description',
                'seo_canonical_url', 'seo_indexable', 'seo_og_title', 'seo_og_description', 'seo_og_image',
            ]);
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
        /** @var ObjectType $record */
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

    private function currentObjectType(): ObjectType
    {
        /** @var ObjectType $record */
        $record = $this->getRecord();

        return $record;
    }
}
