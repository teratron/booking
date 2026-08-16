<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ErrorPages;

use App\Filament\Admin\Resources\ErrorPages\Pages\CreateErrorPage;
use App\Filament\Admin\Resources\ErrorPages\Pages\EditErrorPage;
use App\Filament\Admin\Resources\ErrorPages\Pages\ListErrorPages;
use App\Filament\Admin\Resources\ErrorPages\Schemas\ErrorPageForm;
use App\Filament\Admin\Resources\ErrorPages\Tables\ErrorPagesTable;
use App\Filament\Admin\Resources\Redirects\RedirectResource;
use App\Filament\Admin\Support\ScopedResource;
use App\Models\ErrorPage;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The custom error page copy an SEO specialist administers per `[TZ]` §126 —
 * portal-wide configuration, not scoped to a country, territory, or
 * category, the same posture {@see RedirectResource}
 * takes for the redirect table.
 */
class ErrorPageResource extends ScopedResource
{
    protected static ?string $model = ErrorPage::class;

    protected static string $permissionPrefix = 'seo';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static string|UnitEnum|null $navigationGroup = 'seo';

    public static function getNavigationLabel(): string
    {
        return __('panel.error_pages.title');
    }

    public static function getModelLabel(): string
    {
        return __('panel.error_pages.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.error_pages.title');
    }

    public static function form(Schema $schema): Schema
    {
        return ErrorPageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ErrorPagesTable::configure(self::applyTableDefaults($table));
    }

    /** @return array<string, mixed> */
    public static function getPages(): array
    {
        return [
            'index' => ListErrorPages::route('/'),
            'create' => CreateErrorPage::route('/create'),
            'edit' => EditErrorPage::route('/{record}/edit'),
        ];
    }
}
