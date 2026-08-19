<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Owners\Pages;

use App\Filament\Admin\Exports\OwnerExporter;
use App\Filament\Admin\Resources\Owners\OwnerResource;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;

class ListOwners extends ListRecords
{
    protected static string $resource = OwnerResource::class;

    /** @return array<CreateAction|ExportAction> */
    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make()
                ->label(__('panel.data_transfer.export.actions.trigger'))
                ->exporter(OwnerExporter::class)
                ->authorize(fn (): bool => $this->actor()?->can('user.export') ?? false)
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
