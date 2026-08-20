<?php

declare(strict_types=1);
use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\CabinetPanelProvider;
use App\Providers\HorizonServiceProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    CabinetPanelProvider::class,
    HorizonServiceProvider::class,
];
