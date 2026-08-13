<?php

declare(strict_types=1);

namespace App\Filament\Cabinet\Resources\Photos\Pages;

use App\Filament\Cabinet\Resources\Photos\PhotoResource;
use Filament\Resources\Pages\ListRecords;

/**
 * The whole photo-management screen — see {@see PhotoResource}'s own
 * docblock for why this is the only page the resource registers.
 */
class ListPhotos extends ListRecords
{
    protected static string $resource = PhotoResource::class;
}
