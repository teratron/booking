<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\FinancialRecords\Pages;

use App\Filament\Admin\Resources\FinancialRecords\Exports\FinancialRecordExporter;
use App\Filament\Admin\Resources\FinancialRecords\FinancialRecordResource;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;

class ListFinancialRecords extends ListRecords
{
    protected static string $resource = FinancialRecordResource::class;

    /** @return array<CreateAction|ExportAction> */
    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make()
                ->label(__('panel.financial_records.actions.export'))
                ->exporter(FinancialRecordExporter::class)
                ->authorize(fn (): bool => $this->actor()?->can('finance.export') ?? false)
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
