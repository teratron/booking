<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PlacementTiers\Pages;

use App\Filament\Admin\Resources\PlacementTiers\PlacementTierResource;
use Filament\Resources\Pages\ListRecords;

/**
 * No header actions: the four ranks are seeded and structural, not
 * something an administrator adds to.
 */
class ListPlacementTiers extends ListRecords
{
    protected static string $resource = PlacementTierResource::class;
}
