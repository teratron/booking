<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ApiClients;

use App\Filament\Admin\Resources\ApiClients\Pages\CreateApiClient;
use App\Filament\Admin\Resources\ApiClients\Pages\EditApiClient;
use App\Filament\Admin\Resources\ApiClients\Pages\ListApiClients;
use App\Filament\Admin\Resources\ApiClients\RelationManagers\TokensRelationManager;
use App\Filament\Admin\Resources\ApiClients\Schemas\ApiClientForm;
use App\Filament\Admin\Resources\ApiClients\Tables\ApiClientsTable;
use App\Filament\Admin\Support\ScopedResource;
use App\Models\ApiClient;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The public REST API's consumer registry — who a token belongs to. Token
 * issuance, revocation, and re-scoping happen on {@see TokensRelationManager},
 * not here; this resource only owns the client's own identity fields.
 *
 * Portal-wide administration, not scoped to a country, territory, or
 * category — the same posture {@see ModuleResource} takes for the module
 * registry.
 */
class ApiClientResource extends ScopedResource
{
    protected static ?string $model = ApiClient::class;

    protected static string $permissionPrefix = 'api';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|UnitEnum|null $navigationGroup = 'system';

    /** @var list<string> */
    protected static array $eagerLoad = ['creator'];

    public static function getNavigationLabel(): string
    {
        return __('panel.api_clients.title');
    }

    public static function getModelLabel(): string
    {
        return __('panel.api_clients.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.api_clients.title');
    }

    public static function form(Schema $schema): Schema
    {
        return ApiClientForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ApiClientsTable::configure(self::applyTableDefaults($table));
    }

    /** @return array<class-string> */
    public static function getRelations(): array
    {
        return [
            TokensRelationManager::class,
        ];
    }

    /** @return array<string, mixed> */
    public static function getPages(): array
    {
        return [
            'index' => ListApiClients::route('/'),
            'create' => CreateApiClient::route('/create'),
            'edit' => EditApiClient::route('/{record}/edit'),
        ];
    }
}
