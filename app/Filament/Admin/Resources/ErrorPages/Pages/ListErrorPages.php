<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ErrorPages\Pages;

use App\Filament\Admin\Resources\ErrorPages\ErrorPageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListErrorPages extends ListRecords
{
    protected static string $resource = ErrorPageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
