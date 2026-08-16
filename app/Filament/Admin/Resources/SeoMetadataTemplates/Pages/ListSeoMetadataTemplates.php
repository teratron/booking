<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\SeoMetadataTemplates\Pages;

use App\Filament\Admin\Resources\SeoMetadataTemplates\SeoMetadataTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSeoMetadataTemplates extends ListRecords
{
    protected static string $resource = SeoMetadataTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
