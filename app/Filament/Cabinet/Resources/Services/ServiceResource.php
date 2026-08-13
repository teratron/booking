<?php

declare(strict_types=1);

namespace App\Filament\Cabinet\Resources\Services;

use App\Filament\Cabinet\Resources\Objects\ObjectResource;
use App\Filament\Cabinet\Resources\Services\Pages\EditService;
use App\Filament\Cabinet\Resources\Services\Pages\ListServices;
use App\Filament\Cabinet\Resources\Services\Schemas\ServiceForm;
use App\Filament\Cabinet\Resources\Services\Tables\ServicesTable;
use App\Filament\Cabinet\Support\CabinetResource;
use App\Models\Object_;
use App\Models\Scopes\ModerationScope;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The owner cabinet's service (amenity) selection screen. Like
 * `ObjectResource`, this manages `Object_` directly rather than a separate
 * child model — the content being edited is that same tenant object's own
 * `amenities` relation against the administrator-maintained registry, not a
 * standalone record of its own. No create page for the identical reason
 * `ObjectResource` has none: the tenant object already exists, there is
 * nothing here to create from scratch.
 */
class ServiceResource extends CabinetResource
{
    protected static ?string $model = Object_::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    public static function getNavigationLabel(): string
    {
        return __('panel.cabinet.services.title');
    }

    public static function getModelLabel(): string
    {
        return __('panel.cabinet.services.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.cabinet.services.title');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * `ModerationScope` must be stripped here for the same reason
     * {@see ObjectResource::getEloquentQuery()} strips it: an owner's own
     * not-yet-approved object must stay reachable from every one of the
     * cabinet's own tenant-scoped screens, this one included. The amenity
     * count is eager-loaded for the list column below it — left lazy, the
     * single row this resource ever lists would still cost one avoidable
     * extra query.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScope(ModerationScope::class)
            ->withCount('amenities');
    }

    public static function table(Table $table): Table
    {
        return ServicesTable::configure(self::applyTableDefaults($table));
    }

    public static function form(Schema $schema): Schema
    {
        return ServiceForm::configure($schema);
    }

    /** @return array<string, mixed> */
    public static function getPages(): array
    {
        return [
            'index' => ListServices::route('/'),
            'edit' => EditService::route('/{record}/edit'),
        ];
    }
}
