<?php

declare(strict_types=1);

namespace App\Filament\Cabinet\Resources\Rooms\Pages;

use App\Filament\Cabinet\Resources\Rooms\RoomResource;
use App\Models\Room;
use App\Models\RoomTranslation;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Override;

/**
 * A room edit always applies immediately — see {@see RoomResource}'s own
 * docblock for why room content is never routed through moderation.
 * Translated fields are collected as nested form state
 * (`translations.{locale}.*`) and reconciled against the real
 * `room_translations` rows here, exactly as the object edit page's own
 * mechanism does.
 */
class EditRoom extends EditRecord
{
    protected static string $resource = RoomResource::class;

    /** @var array<string, array<string, mixed>> */
    private array $pendingTranslations = [];

    /** @return array<string, mixed> */
    #[Override]
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var RoomTranslation $translation */
        foreach ($this->currentRoom()->translations as $translation) {
            $data['translations'][$translation->locale] = $translation->only(['name', 'description']);
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
        /** @var Room $record */
        $record->fill($data);
        $record->save();

        foreach ($this->pendingTranslations as $locale => $fields) {
            $fields = array_filter($fields, static fn (mixed $value): bool => $value !== null && $value !== '');

            if ($fields === []) {
                continue;
            }

            $record->translations()->updateOrCreate(['locale' => $locale], $fields);
        }

        return $record;
    }

    private function currentRoom(): Room
    {
        /** @var Room $record */
        $record = $this->getRecord();

        return $record;
    }
}
