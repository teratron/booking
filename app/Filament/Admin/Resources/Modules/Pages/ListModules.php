<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Modules\Pages;

use App\Filament\Admin\Resources\Modules\ModuleResource;
use Filament\Resources\Pages\ListRecords;

/**
 * No header actions: the registry is not something an administrator adds to.
 */
class ListModules extends ListRecords
{
    protected static string $resource = ModuleResource::class;
}
