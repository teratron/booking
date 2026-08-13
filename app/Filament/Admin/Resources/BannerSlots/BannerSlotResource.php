<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BannerSlots;

use App\Filament\Admin\Resources\BannerSlots\Pages\CreateBannerSlot;
use App\Filament\Admin\Resources\BannerSlots\Pages\EditBannerSlot;
use App\Filament\Admin\Resources\BannerSlots\Pages\ListBannerSlots;
use App\Filament\Admin\Resources\BannerSlots\Schemas\BannerSlotForm;
use App\Filament\Admin\Resources\BannerSlots\Tables\BannerSlotsTable;
use App\Filament\Admin\Support\ScopedResource;
use App\Models\BannerSlot;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The inventory-position registry. Unlike the placement tiers' four fixed
 * structural rows, slots are administrator-created data — full CRUD, since
 * adding a new page-kind position is exactly the kind of change this
 * resource exists to make code-free. Declares no scope axis: a slot is
 * portal-wide inventory, not owned by any one country, territory, or
 * category, so only an unrestricted grant reaches it.
 */
class BannerSlotResource extends ScopedResource
{
    protected static ?string $model = BannerSlot::class;

    protected static string $permissionPrefix = 'advertising';

    /** @var list<string> */
    protected static array $eagerLoad = ['translations'];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static string|UnitEnum|null $navigationGroup = 'advertising';

    public static function getNavigationLabel(): string
    {
        return __('panel.banner_slots.title');
    }

    public static function getModelLabel(): string
    {
        return __('panel.banner_slots.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.banner_slots.title');
    }

    public static function form(Schema $schema): Schema
    {
        return BannerSlotForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BannerSlotsTable::configure(self::applyTableDefaults($table));
    }

    /** @return array<string, mixed> */
    public static function getPages(): array
    {
        return [
            'index' => ListBannerSlots::route('/'),
            'create' => CreateBannerSlot::route('/create'),
            'edit' => EditBannerSlot::route('/{record}/edit'),
        ];
    }
}
