<?php

declare(strict_types=1);

namespace App\Filament\Cabinet\Resources\NewsItems\Pages;

use App\Filament\Cabinet\Resources\NewsItems\NewsItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListNewsItems extends ListRecords
{
    protected static string $resource = NewsItemResource::class;

    /** @return array<CreateAction> */
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
