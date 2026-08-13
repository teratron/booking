<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PromotionLabels\Pages;

use App\Filament\Admin\Resources\PromotionLabels\PromotionLabelResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPromotionLabels extends ListRecords
{
    protected static string $resource = PromotionLabelResource::class;

    /** @return array<CreateAction> */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
