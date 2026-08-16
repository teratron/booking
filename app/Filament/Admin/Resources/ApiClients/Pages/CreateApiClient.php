<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ApiClients\Pages;

use App\Filament\Admin\Resources\ApiClients\ApiClientResource;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Override;

class CreateApiClient extends CreateRecord
{
    protected static string $resource = ApiClientResource::class;

    /** @return array<string, mixed> */
    #[Override]
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $actor = Filament::auth()->user();

        if ($actor instanceof User) {
            $data['created_by'] = $actor->id;
        }

        return $data;
    }
}
