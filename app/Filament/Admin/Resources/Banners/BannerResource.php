<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Banners;

use App\Filament\Admin\Resources\Banners\Pages\CreateBanner;
use App\Filament\Admin\Resources\Banners\Pages\EditBanner;
use App\Filament\Admin\Resources\Banners\Pages\ListBanners;
use App\Filament\Admin\Resources\Banners\Schemas\BannerForm;
use App\Filament\Admin\Resources\Banners\Tables\BannersTable;
use App\Filament\Admin\Support\ScopedResource;
use App\Models\Banner;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Advertising creatives sold and managed portal-wide. Declares no scope
 * axis: a campaign is not owned by any one country, territory, or object
 * category, so only an unrestricted grant reaches it — selection (which
 * eligible banner actually serves for a given request) is a separate,
 * later concern this resource does not implement.
 */
class BannerResource extends ScopedResource
{
    protected static ?string $model = Banner::class;

    protected static string $permissionPrefix = 'advertising';

    /**
     * `territories`/`categories`/`targetLanguages` are read only by their own
     * multi-select form fields, never by a table column, but strict mode
     * still turns the lazy load they would otherwise trigger into a thrown
     * exception the first time an edit page opens.
     *
     * @var list<string>
     */
    protected static array $eagerLoad = [
        'translations',
        'slot.translations',
        'territories.translations',
        'categories.translations',
        'targetLanguages',
    ];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|UnitEnum|null $navigationGroup = 'advertising';

    public static function getNavigationLabel(): string
    {
        return __('panel.banners.title');
    }

    public static function getModelLabel(): string
    {
        return __('panel.banners.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.banners.title');
    }

    public static function form(Schema $schema): Schema
    {
        return BannerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BannersTable::configure(self::applyTableDefaults($table));
    }

    /** @return array<string, mixed> */
    public static function getPages(): array
    {
        return [
            'index' => ListBanners::route('/'),
            'create' => CreateBanner::route('/create'),
            'edit' => EditBanner::route('/{record}/edit'),
        ];
    }
}
