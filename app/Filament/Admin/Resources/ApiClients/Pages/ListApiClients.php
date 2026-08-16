<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ApiClients\Pages;

use App\Filament\Admin\Resources\ApiClients\ApiClientResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListApiClients extends ListRecords
{
    protected static string $resource = ApiClientResource::class;

    /** @return array<CreateAction> */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
