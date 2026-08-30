<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\SeoMetadataTemplates\Pages;

use App\Filament\Admin\Resources\SeoMetadataTemplates\SeoMetadataTemplateResource;
use App\Models\SeoMetadataTemplate;
use App\Services\Seo\MetadataResolver;
use App\Support\Seo\SeoEntityType;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Override;

class EditSeoMetadataTemplate extends EditRecord
{
    protected static string $resource = SeoMetadataTemplateResource::class;

    /** @var array{entityType: string, locale: string}|null */
    private ?array $originalCacheKey = null;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->after(function (SeoMetadataTemplate $record): void {
                    app(MetadataResolver::class)->forgetTemplateCache(
                        SeoEntityType::from((string) $record->entity_type),
                        (string) $record->locale,
                    );
                }),
        ];
    }

    /**
     * Captures the record's still-unmodified entity type and locale, not
     * {@see mutateFormDataBeforeFill()} — that hook runs during the page's
     * initial `mount()`, a separate Livewire request from the one that
     * calls `afterSave()` below, and this class's `$originalCacheKey` is a
     * plain, unpersisted property: Livewire only carries public properties
     * across that request boundary, so a value captured at mount time
     * would already be back to null by the time save runs. Reading it here
     * instead — one step before {@see handleRecordUpdate()} overwrites the
     * in-memory record — keeps capture and use inside the same request.
     *
     * @return array<string, mixed>
     */
    #[Override]
    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var SeoMetadataTemplate $record */
        $record = $this->record;

        $this->originalCacheKey = ['entityType' => (string) $record->entity_type, 'locale' => (string) $record->locale];

        return $data;
    }

    /**
     * Drops both the pre-edit and post-edit cache entries — an edit that
     * also changes which entity kind or language a template targets must
     * not leave a stale copy under the old key. Not a real method
     * override — Filament's page lifecycle discovers this hook by name via
     * `method_exists()`, not through the class hierarchy, so `#[Override]`
     * does not apply here.
     */
    protected function afterSave(): void
    {
        /** @var SeoMetadataTemplate $record */
        $record = $this->record;
        $resolver = app(MetadataResolver::class);

        if ($this->originalCacheKey !== null) {
            $resolver->forgetTemplateCache(
                SeoEntityType::from($this->originalCacheKey['entityType']),
                $this->originalCacheKey['locale'],
            );
        }

        $resolver->forgetTemplateCache(
            SeoEntityType::from((string) $record->entity_type),
            (string) $record->locale,
        );
    }
}
