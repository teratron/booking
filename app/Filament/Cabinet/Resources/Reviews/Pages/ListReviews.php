<?php

declare(strict_types=1);

namespace App\Filament\Cabinet\Resources\Reviews\Pages;

use App\Filament\Cabinet\Resources\Reviews\ReviewResource;
use Filament\Resources\Pages\ListRecords;

/**
 * The whole review-management screen — see {@see ReviewResource}'s own
 * docblock for why this is the only page the resource registers.
 */
class ListReviews extends ListRecords
{
    protected static string $resource = ReviewResource::class;
}
