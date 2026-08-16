<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\CatalogFilterPromotions\Pages;

use App\Filament\Admin\Resources\CatalogFilterPromotions\CatalogFilterPromotionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCatalogFilterPromotions extends ListRecords
{
    protected static string $resource = CatalogFilterPromotionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
