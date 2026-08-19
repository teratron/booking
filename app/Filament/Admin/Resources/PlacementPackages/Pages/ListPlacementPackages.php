<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PlacementPackages\Pages;

use App\Filament\Admin\Exports\PlacementPackageExporter;
use App\Filament\Admin\Resources\PlacementPackages\PlacementPackageResource;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;

class ListPlacementPackages extends ListRecords
{
    protected static string $resource = PlacementPackageResource::class;

    /** @return array<CreateAction|ExportAction> */
    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make()
                ->label(__('panel.data_transfer.export.actions.trigger'))
                ->exporter(PlacementPackageExporter::class)
                ->authorize(fn (): bool => $this->actor()?->can('commerce.export') ?? false)
                ->options(fn (): array => ['filters' => $this->tableFilters ?? []]),
            CreateAction::make(),
        ];
    }

    private function actor(): ?User
    {
        $user = Filament::auth()->user();

        return $user instanceof User ? $user : null;
    }
}
