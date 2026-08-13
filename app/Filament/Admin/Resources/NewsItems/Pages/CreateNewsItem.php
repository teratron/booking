<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\NewsItems\Pages;

use App\Filament\Admin\Resources\NewsItems\NewsItemResource;
use App\Models\NewsItem;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Override;

class CreateNewsItem extends CreateRecord
{
    protected static string $resource = NewsItemResource::class;

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
        /** @var NewsItem $record */
        $record = parent::handleRecordCreation($data);

        foreach ($this->pendingTranslations as $locale => $fields) {
            $fields = array_filter($fields, static fn ($value): bool => $value !== null && $value !== '');

            if ($fields === []) {
                continue;
            }

            $fields['slug'] ??= Str::slug($fields['title'] ?? (string) $record->id);

            $record->translations()->create(['locale' => $locale, ...$fields]);
        }

        return $record;
    }
}
