<?php

declare(strict_types=1);

namespace App\Filament\Cabinet\Resources\Rooms\Pages;

use App\Filament\Cabinet\Resources\Rooms\RoomResource;
use App\Models\Room;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Override;

/**
 * A new room category always applies immediately — see
 * {@see RoomResource}'s own docblock for why room content is never routed
 * through moderation. The tenant object itself is associated automatically
 * by Filament's own tenancy (via {@see Room::object()}), so this page's own
 * job is limited to reconciling the per-language name/description bucket
 * against the real `room_translations` rows, exactly as the object create
 * flow's identical mechanism does.
 */
class CreateRoom extends CreateRecord
{
    protected static string $resource = RoomResource::class;

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
        /** @var Room $record */
        $record = static::getModel()::create($data);

        foreach ($this->pendingTranslations as $locale => $fields) {
            $fields = array_filter($fields, static fn (mixed $value): bool => $value !== null && $value !== '');

            if ($fields === []) {
                continue;
            }

            $record->translations()->create(['locale' => $locale, ...$fields]);
        }

        return $record;
    }
}
