<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ActionJournal\Pages;

use App\Filament\Admin\Resources\ActionJournal\ActionJournalResource;
use App\Filament\Admin\Resources\ActionJournal\Exports\ActionJournalExporter;
use App\Models\User;
use Filament\Actions\ExportAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;

class ListActionJournal extends ListRecords
{
    protected static string $resource = ActionJournalResource::class;

    /** @return array<int, ExportAction> */
    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make()
                ->label(__('panel.action_journal.actions.export'))
                ->exporter(ActionJournalExporter::class)
                ->authorize(fn (): bool => $this->actor()?->can('audit.export') ?? false),
        ];
    }

    private function actor(): ?User
    {
        $user = Filament::auth()->user();

        return $user instanceof User ? $user : null;
    }
}
