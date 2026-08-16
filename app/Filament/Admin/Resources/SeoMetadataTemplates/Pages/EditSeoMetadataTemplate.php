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

    /** @return array<string, mixed> */
    #[Override]
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $this->originalCacheKey = ['entityType' => (string) $data['entity_type'], 'locale' => (string) $data['locale']];

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
