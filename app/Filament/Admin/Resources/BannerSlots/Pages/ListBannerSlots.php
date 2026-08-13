<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BannerSlots\Pages;

use App\Filament\Admin\Resources\BannerSlots\BannerSlotResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBannerSlots extends ListRecords
{
    protected static string $resource = BannerSlotResource::class;

    /** @return array<CreateAction> */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
