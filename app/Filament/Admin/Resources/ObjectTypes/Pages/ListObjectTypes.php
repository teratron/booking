<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ObjectTypes\Pages;

use App\Filament\Admin\Resources\ObjectTypes\ObjectTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListObjectTypes extends ListRecords
{
    protected static string $resource = ObjectTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
