<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Objects\Pages;

use App\Filament\Admin\Resources\Objects\ObjectResource;
use Filament\Resources\Pages\ListRecords;

class ListObjects extends ListRecords
{
    protected static string $resource = ObjectResource::class;
}
