<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Territories\Pages;

use App\Filament\Admin\Resources\Territories\TerritoryResource;
use App\Models\Territory;
use App\Services\Geography\TerritoryAdministrator;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Override;

/**
 * Translated fields arrive as nested form state (`translations.{locale}.*`)
 * and are written to `territory_translations` explicitly here, mirroring the
 * object form — `astrotomic/laravel-translatable` has no Filament
 * relationship binding of its own.
 */
class CreateTerritory extends CreateRecord
{
    protected static string $resource = TerritoryResource::class;

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
        /** @var Territory $record */
        $record = parent::handleRecordCreation($data);

        foreach ($this->pendingTranslations as $locale => $fields) {
            $fields = array_filter($fields, static fn ($value): bool => $value !== null && $value !== '');

            if (! isset($fields['name'])) {
                continue;
            }

            $fields['slug'] ??= Str::slug($fields['name']).'-'.$record->id;

            // country_id is denormalized onto every translation row (see the
            // migration that added it) and has no database default — without
            // setting it here, the very first translation ever written for a
            // freshly created territory violates the NOT NULL constraint.
            $record->translations()->create(['locale' => $locale, 'country_id' => $record->country_id, ...$fields]);
        }

        /** @var Territory $reloaded */
        $reloaded = $record->fresh();

        app(TerritoryAdministrator::class)->recomputeSlugPaths($reloaded);

        return $record;
    }
}
