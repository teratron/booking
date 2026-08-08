<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Languages;

use App\Filament\Admin\Resources\Languages\Pages\ListLanguages;
use App\Filament\Admin\Resources\Languages\Tables\LanguagesTable;
use App\Filament\Admin\Support\ScopedResource;
use App\Models\Language;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The language registry. Declares no scope axis — like
 * `ObjectTypeResource`, it is portal-wide configuration reachable only by
 * an unrestricted grant. No create or delete page: the registry's
 * membership is fixed at seed time (activating a language later is a data
 * change, never a new row), and the only administrator actions are toggling
 * active, setting the primary, and reordering — all available directly
 * from the list.
 */
class LanguageResource extends ScopedResource
{
    protected static ?string $model = Language::class;

    protected static string $permissionPrefix = 'settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLanguage;

    protected static string|UnitEnum|null $navigationGroup = 'system';

    public static function getNavigationLabel(): string
    {
        return __('panel.languages.title');
    }

    public static function getModelLabel(): string
    {
        return __('panel.languages.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.languages.title');
    }

    public static function table(Table $table): Table
    {
        return LanguagesTable::configure(self::applyTableDefaults($table));
    }

    /** @return array<string, mixed> */
    public static function getPages(): array
    {
        return [
            'index' => ListLanguages::route('/'),
        ];
    }
}
