<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Modules;

use App\Filament\Admin\Resources\Modules\Pages\ListModules;
use App\Filament\Admin\Resources\Modules\Tables\ModulesTable;
use App\Filament\Admin\Support\ScopedResource;
use App\Models\Module;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The module registry, listed with its effective state at each scope and the
 * toggles that change it.
 *
 * There is no create or edit screen, and that is the design rather than an
 * omission: a module exists because code implements it, so adding a row here
 * would register a capability nothing can deliver. What an administrator
 * changes is state, not membership.
 *
 * Declares no scope axis. A module is portal-wide configuration, and a grant
 * bounded to one country says nothing about who may switch a capability off
 * for the whole portal — so only an unrestricted grant reaches this list.
 */
class ModuleResource extends ScopedResource
{
    protected static ?string $model = Module::class;

    protected static string $permissionPrefix = 'settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    protected static string|UnitEnum|null $navigationGroup = 'system';

    public static function getNavigationLabel(): string
    {
        return __('panel.modules.title');
    }

    public static function getModelLabel(): string
    {
        return __('panel.modules.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.modules.title');
    }

    /** @var list<string> */
    protected static array $eagerLoad = ['translations', 'dependencies'];

    public static function table(Table $table): Table
    {
        return ModulesTable::configure(self::applyTableDefaults($table));
    }

    /** @return array<string, mixed> */
    public static function getPages(): array
    {
        return [
            'index' => ListModules::route('/'),
        ];
    }
}
