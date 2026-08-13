<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\NewsItems\Pages;

use App\Filament\Admin\Resources\NewsItems\NewsItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListNewsItems extends ListRecords
{
    protected static string $resource = NewsItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
