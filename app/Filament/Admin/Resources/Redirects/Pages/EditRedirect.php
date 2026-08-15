<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Redirects\Pages;

use App\Filament\Admin\Resources\Redirects\RedirectResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRedirect extends EditRecord
{
    protected static string $resource = RedirectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
