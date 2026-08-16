<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\SeoMetadataTemplates\Pages;

use App\Filament\Admin\Resources\SeoMetadataTemplates\SeoMetadataTemplateResource;
use App\Models\SeoMetadataTemplate;
use App\Services\Seo\MetadataResolver;
use App\Support\Seo\SeoEntityType;
use Filament\Resources\Pages\CreateRecord;

class CreateSeoMetadataTemplate extends CreateRecord
{
    protected static string $resource = SeoMetadataTemplateResource::class;

    /**
     * A new template must take effect immediately — a specialist adding one
     * to fix a bare-derivation title should not have to wait for whatever
     * TTL the read side would otherwise carry. Not a real method override —
     * Filament's page lifecycle discovers this hook by name via
     * `method_exists()`, not through the class hierarchy, so `#[Override]`
     * does not apply here.
     */
    protected function afterCreate(): void
    {
        /** @var SeoMetadataTemplate $record */
        $record = $this->record;

        app(MetadataResolver::class)->forgetTemplateCache(
            SeoEntityType::from((string) $record->entity_type),
            (string) $record->locale,
        );
    }
}
