<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ErrorPages\Pages;

use App\Filament\Admin\Resources\ErrorPages\ErrorPageResource;
use App\Models\ErrorPage;
use App\Models\ErrorPageTranslation;
use App\Services\Seo\ErrorPageResolver;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Override;

/**
 * Reconciles the per-language title/body bucket against the real
 * `error_page_translations` rows, exactly as the room edit flow's identical
 * mechanism does, then drops the resolver's cache for every language
 * touched — under the original status code too, when the edit changed
 * which status code the record targets.
 */
class EditErrorPage extends EditRecord
{
    protected static string $resource = ErrorPageResource::class;

    /** @var array<string, array<string, mixed>> */
    private array $pendingTranslations = [];

    private ?int $originalStatusCode = null;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->after(function (ErrorPage $record): void {
                $resolver = app(ErrorPageResolver::class);

                /** @var ErrorPageTranslation $translation */
                foreach ($record->translations as $translation) {
                    $resolver->forgetCache((int) $record->status_code, (string) $translation->locale);
                }
            }),
        ];
    }

    /** @return array<string, mixed> */
    #[Override]
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $this->originalStatusCode = isset($data['status_code']) ? (int) $data['status_code'] : null;

        /** @var ErrorPageTranslation $translation */
        foreach ($this->currentErrorPage()->translations as $translation) {
            $data['translations'][$translation->locale] = $translation->only(['title', 'body']);
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
        /** @var ErrorPage $record */
        $record->fill($data);
        $record->save();

        $resolver = app(ErrorPageResolver::class);
        $newStatusCode = (int) $record->status_code;

        foreach ($this->pendingTranslations as $locale => $fields) {
            $fields = array_filter($fields, static fn (mixed $value): bool => $value !== null && $value !== '');

            if ($fields === []) {
                continue;
            }

            $record->translations()->updateOrCreate(['locale' => $locale], $fields);
            $resolver->forgetCache($newStatusCode, (string) $locale);

            if ($this->originalStatusCode !== null && $this->originalStatusCode !== $newStatusCode) {
                $resolver->forgetCache($this->originalStatusCode, (string) $locale);
            }
        }

        return $record;
    }

    private function currentErrorPage(): ErrorPage
    {
        /** @var ErrorPage $record */
        $record = $this->getRecord();

        return $record;
    }
}
