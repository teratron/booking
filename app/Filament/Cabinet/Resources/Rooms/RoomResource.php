<?php

declare(strict_types=1);

namespace App\Filament\Cabinet\Resources\Rooms;

use App\Filament\Cabinet\Resources\Rooms\Pages\CreateRoom;
use App\Filament\Cabinet\Resources\Rooms\Pages\EditRoom;
use App\Filament\Cabinet\Resources\Rooms\Pages\ListRooms;
use App\Filament\Cabinet\Resources\Rooms\Schemas\RoomForm;
use App\Filament\Cabinet\Resources\Rooms\Tables\RoomsTable;
use App\Filament\Cabinet\Support\CabinetResource;
use App\Models\Room;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The owner cabinet's room-category management screen — an unbounded set of
 * rooms (each with its own capacity, amenities, and price list) belonging to
 * the currently selected tenant object. Reachable only when that object's
 * declared type carries the `has_rooms` structural capability: a restaurant
 * or a museum has no room list to manage, so this screen must not exist for
 * one. That check is enforced twice, deliberately — once for navigation
 * ({@see self::shouldRegisterNavigation()}) and independently for every one
 * of this resource's own routes ({@see self::canAccess()}), because hiding a
 * menu entry is a usability affordance, never the access control.
 *
 * Every write here — a room's own fields, its translations, its amenities,
 * and every price attached to it — takes effect immediately, regardless of
 * whether the parent object is already published. This is a deliberate
 * choice, not an oversight: the object-editing moderation pipeline can only
 * safely replay a flat set of columns (or locale-keyed translation buckets)
 * back onto a single record via `fill()` + `save()`. A room category is a
 * genuinely relational structure — a set of rows, each with its own nested
 * translations and its own nested set of price rows — which that replay
 * mechanism has no way to reconstruct; routing it through the same pipeline
 * used for the object's own fields would either drop the change silently or
 * crash a moderator's later approval with an unknown-column error. Room and
 * price data is therefore exempted from moderation the same way an object's
 * own contact channels are: it applies directly, every time.
 */
class RoomResource extends CabinetResource
{
    protected static ?string $model = Room::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    public static function getNavigationLabel(): string
    {
        return __('panel.cabinet.rooms.title');
    }

    public static function getModelLabel(): string
    {
        return __('panel.cabinet.rooms.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.cabinet.rooms.title');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return parent::shouldRegisterNavigation() && self::currentObjectHasCapability('has_rooms');
    }

    /**
     * The route-level counterpart to {@see self::shouldRegisterNavigation()}
     * — every page this resource registers authorizes through this method
     * (directly, for the list and create pages; via the edit page's own
     * additional record check), so a capability-less tenant is refused with
     * a 403 for a typed-in URL exactly as it is kept off the menu.
     */
    public static function canAccess(): bool
    {
        return parent::canAccess() && self::currentObjectHasCapability('has_rooms');
    }

    /**
     * Two relations are eager-loaded, each for its own row-level reason: the
     * owning object, since every row's `EditAction`/`DeleteAction` resolves
     * through `RoomPolicy`, which dereferences `Room::object()` to reach the
     * object's scope attributes; and the translations astrotomic's own
     * `name` accessor reads for the table's name column — left lazy, either
     * one fires one extra query per row.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['object', 'translations']);
    }

    public static function table(Table $table): Table
    {
        return RoomsTable::configure(self::applyTableDefaults($table));
    }

    public static function form(Schema $schema): Schema
    {
        return RoomForm::configure($schema);
    }

    /** @return array<string, mixed> */
    public static function getPages(): array
    {
        return [
            'index' => ListRooms::route('/'),
            'create' => CreateRoom::route('/create'),
            'edit' => EditRoom::route('/{record}/edit'),
        ];
    }
}
