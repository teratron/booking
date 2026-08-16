<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ApiClients\Pages;

use App\Filament\Admin\Resources\ApiClients\ApiClientResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditApiClient extends EditRecord
{
    protected static string $resource = ApiClientResource::class;

    /** @return array<DeleteAction> */
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
