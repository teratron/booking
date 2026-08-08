<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ModerationRequests\Pages;

use App\Filament\Admin\Resources\ModerationRequests\ModerationRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListModerationRequests extends ListRecords
{
    protected static string $resource = ModerationRequestResource::class;
}
