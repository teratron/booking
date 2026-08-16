<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ErrorPages\Pages;

use App\Filament\Admin\Resources\ErrorPages\ErrorPageResource;
use App\Models\ErrorPage;
use App\Services\Seo\ErrorPageResolver;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Override;

/**
 * Reconciles the per-language title/body bucket against the real
 * `error_page_translations` rows, exactly as the room create flow's
 * identical mechanism does, then drops the resolver's cache for every
 * language just written so the new override takes effect immediately.
 */
class CreateErrorPage extends CreateRecord
{
    protected static string $resource = ErrorPageResource::class;

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
        /** @var ErrorPage $record */
        $record = static::getModel()::create($data);
        $resolver = app(ErrorPageResolver::class);

        foreach ($this->pendingTranslations as $locale => $fields) {
            $fields = array_filter($fields, static fn (mixed $value): bool => $value !== null && $value !== '');

            if ($fields === []) {
                continue;
            }

            $record->translations()->create(['locale' => $locale, ...$fields]);
            $resolver->forgetCache((int) $record->status_code, (string) $locale);
        }

        return $record;
    }
}
