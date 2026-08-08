<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ActionJournal;

use App\Filament\Admin\Resources\ActionJournal\Pages\ListActionJournal;
use App\Filament\Admin\Resources\ActionJournal\Tables\ActionJournalTable;
use App\Filament\Admin\Support\ScopedResource;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use OwenIt\Auditing\Models\Audit;
use UnitEnum;

/**
 * The action journal — every privileged mutation this panel records, read
 * through the one table `owen-it/laravel-auditing`'s own writes and
 * `AuditJournal`'s explicit writes both land in. Gated on `audit.view`, a
 * standalone permission distinct from every resource's own CRUD verbs,
 * since who may change a record and who may read the portal-wide history of
 * every change are different questions. Declares no scope axis — like the
 * language and module registries, an unrestricted grant is what reaches it.
 * List-only: a journal entry is never created, edited, or deleted by hand,
 * and the database enforces that append-only guarantee itself.
 */
class ActionJournalResource extends ScopedResource
{
    protected static ?string $model = Audit::class;

    protected static string $permissionPrefix = 'audit';

    /** @var list<string> */
    protected static array $eagerLoad = ['user'];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'governance';

    public static function getNavigationLabel(): string
    {
        return __('panel.action_journal.title');
    }

    public static function getModelLabel(): string
    {
        return __('panel.action_journal.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.action_journal.title');
    }

    public static function table(Table $table): Table
    {
        return ActionJournalTable::configure(self::applyTableDefaults($table));
    }

    /** @return array<string, mixed> */
    public static function getPages(): array
    {
        return [
            'index' => ListActionJournal::route('/'),
        ];
    }
}
