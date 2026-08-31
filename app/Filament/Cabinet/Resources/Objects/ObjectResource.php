<?php

declare(strict_types=1);

namespace App\Filament\Cabinet\Resources\Objects;

use App\Filament\Cabinet\Resources\Objects\Pages\EditObject;
use App\Filament\Cabinet\Resources\Objects\Pages\ListObjects;
use App\Filament\Cabinet\Resources\Objects\Schemas\ObjectForm;
use App\Filament\Cabinet\Resources\Objects\Tables\ObjectsTable;
use App\Filament\Cabinet\Support\CabinetResource;
use App\Models\Object_;
use App\Models\Scopes\ModerationScope;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The owner cabinet's own object-editing screen. Unlike every other
 * tenant-scoped cabinet resource, this one manages `Object_` directly —
 * Filament's tenancy narrows its queries to the tenant's own primary key
 * automatically (see `BelongsToTenant::scopeEloquentQueryToTenant()`), the
 * same mechanism that scopes a child resource by the `object` relationship.
 *
 * No create page: objects are staff-provisioned (via the admin panel's own
 * Objects resource), never self-created through the cabinet.
 */
class ObjectResource extends CabinetResource
{
    protected static ?string $model = Object_::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static ?int $navigationSort = -1;

    public static function getNavigationLabel(): string
    {
        return __('panel.cabinet.objects.title');
    }

    public static function getModelLabel(): string
    {
        return __('panel.cabinet.objects.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.cabinet.objects.title');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * `ModerationScope` exists to keep unapproved content off public pages —
     * it must never hide an owner's own object from their own cabinet. Left
     * unstripped here, an owner whose object is still pending, rejected, or
     * awaiting revision could never reach the very screen that lets them act
     * on it, the exact failure `CabinetPanelProvider`'s own tenant resolution
     * already had to be fixed for.
     */
    public static function getEloquentQuery(): Builder
    {
        // `translations` eager-loaded: the list column reads the object's
        // translated `name` per row, a lazy-loading violation under strict
        // mode without it.
        return parent::getEloquentQuery()
            ->withoutGlobalScope(ModerationScope::class)
            ->with('translations');
    }

    public static function table(Table $table): Table
    {
        return ObjectsTable::configure(self::applyTableDefaults($table));
    }

    public static function form(Schema $schema): Schema
    {
        return ObjectForm::configure($schema);
    }

    /** @return array<string, mixed> */
    public static function getPages(): array
    {
        return [
            'index' => ListObjects::route('/'),
            'edit' => EditObject::route('/{record}/edit'),
        ];
    }
}
