<?php

declare(strict_types=1);

namespace App\Filament\Cabinet\Resources\Notifications\Pages;

use App\Filament\Cabinet\Resources\Notifications\NotificationResource;
use Filament\Resources\Pages\ListRecords;

/**
 * The whole notification inbox screen — see {@see NotificationResource}'s
 * own docblock for why this is the only page the resource registers.
 */
class ListNotifications extends ListRecords
{
    protected static string $resource = NotificationResource::class;
}
