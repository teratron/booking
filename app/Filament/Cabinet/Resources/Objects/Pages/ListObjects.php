<?php

declare(strict_types=1);

namespace App\Filament\Cabinet\Resources\Objects\Pages;

use App\Filament\Cabinet\Resources\Objects\ObjectResource;
use Filament\Resources\Pages\ListRecords;

/**
 * Always exactly one row — Filament's tenancy scopes this resource's query
 * to the current tenant object's own primary key. No header actions: there
 * is nothing to create (objects are staff-provisioned) and nothing to bulk
 * act on.
 */
class ListObjects extends ListRecords
{
    protected static string $resource = ObjectResource::class;
}
