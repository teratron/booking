<?php

declare(strict_types=1);

namespace App\Filament\Cabinet\Resources\Services\Pages;

use App\Filament\Cabinet\Resources\Services\ServiceResource;
use Filament\Resources\Pages\ListRecords;

/**
 * Always exactly one row — Filament's own tenancy scopes this resource's
 * query to the current tenant object's own primary key, mirroring
 * `ListObjects`'s identical reasoning. No header actions: there is nothing
 * to create and nothing to bulk act on.
 */
class ListServices extends ListRecords
{
    protected static string $resource = ServiceResource::class;
}
