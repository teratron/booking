<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Redirects\Pages;

use App\Filament\Admin\Resources\Redirects\RedirectResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRedirect extends CreateRecord
{
    protected static string $resource = RedirectResource::class;
}
