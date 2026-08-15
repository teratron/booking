<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Redirects;

use App\Filament\Admin\Resources\ObjectTypes\ObjectTypeResource;
use App\Filament\Admin\Resources\Redirects\Pages\CreateRedirect;
use App\Filament\Admin\Resources\Redirects\Pages\EditRedirect;
use App\Filament\Admin\Resources\Redirects\Pages\ListRedirects;
use App\Filament\Admin\Resources\Redirects\Schemas\RedirectForm;
use App\Filament\Admin\Resources\Redirects\Tables\RedirectsTable;
use App\Filament\Admin\Support\ScopedResource;
use App\Models\Redirect;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The redirect table's own administration screen. Portal-wide configuration,
 * not scoped to a country, territory, or category — the same posture
 * {@see ObjectTypeResource} takes
 * for the type registry.
 */
class RedirectResource extends ScopedResource
{
    protected static ?string $model = Redirect::class;

    protected static string $permissionPrefix = 'settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUturnLeft;

    protected static string|UnitEnum|null $navigationGroup = 'geography';

    public static function getNavigationLabel(): string
    {
        return __('panel.redirects.title');
    }

    public static function getModelLabel(): string
    {
        return __('panel.redirects.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.redirects.title');
    }

    public static function form(Schema $schema): Schema
    {
        return RedirectForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RedirectsTable::configure(self::applyTableDefaults($table));
    }

    /** @return array<string, mixed> */
    public static function getPages(): array
    {
        return [
            'index' => ListRedirects::route('/'),
            'create' => CreateRedirect::route('/create'),
            'edit' => EditRedirect::route('/{record}/edit'),
        ];
    }
}
