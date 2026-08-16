<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ApiClients\RelationManagers;

use App\Models\ApiClient;
use App\Models\ApiToken;
use App\Models\Country;
use App\Models\ObjectType;
use App\Models\User;
use App\Services\Api\ApiTokenService;
use App\Support\Api\ApiResource;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Override;

/**
 * Token issuance, revocation, and re-scoping for one {@see ApiClient} — the
 * only place any of the three happens, so {@see ApiTokenService} is the only
 * writer and the action journal never has a gap.
 *
 * Neither issuance nor scope change uses the resource's own Create/Edit
 * machinery: an issued token's plain-text value must reach the administrator
 * exactly once and is never itself a model attribute, which the built-in
 * record form has no way to express.
 */
class TokensRelationManager extends RelationManager
{
    protected static string $relationship = 'tokens';

    #[Override]
    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('panel.api_clients.tokens.title');
    }

    #[Override]
    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    #[Override]
    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('scopes')->latest('id'))
            ->columns(self::columns())
            ->headerActions([$this->issueAction()])
            ->recordActions([$this->editScopeAction(), $this->revokeAction()]);
    }

    /** @return array<TextColumn|IconColumn> */
    private static function columns(): array
    {
        return [
            TextColumn::make('name')
                ->label(__('panel.api_clients.tokens.name')),

            TextColumn::make('abilities')
                ->label(__('panel.api_clients.tokens.resources'))
                ->badge(),

            TextColumn::make('scope_countries')
                ->label(__('panel.api_clients.tokens.countries'))
                ->getStateUsing(fn (ApiToken $record): string => self::scopeLabels($record, 'country', self::countryLabels())),

            TextColumn::make('scope_categories')
                ->label(__('panel.api_clients.tokens.categories'))
                ->getStateUsing(fn (ApiToken $record): string => self::scopeLabels($record, 'category', self::categoryLabels())),

            TextColumn::make('rate_limit_per_minute')
                ->label(__('panel.api_clients.tokens.rate_limit'))
                ->placeholder(__('panel.api_clients.tokens.rate_limit_default')),

            TextColumn::make('expires_at')
                ->label(__('panel.api_clients.tokens.expires_at'))
                ->dateTime()
                ->placeholder('—'),

            TextColumn::make('last_used_at')
                ->label(__('panel.api_clients.tokens.last_used_at'))
                ->dateTime()
                ->placeholder(__('panel.api_clients.tokens.never_used')),

            IconColumn::make('is_revoked')
                ->label(__('panel.api_clients.tokens.revoked'))
                ->boolean()
                ->getStateUsing(fn (ApiToken $record): bool => $record->isRevoked()),
        ];
    }

    /**
     * @param  array<int, string>  $labels
     */
    private static function scopeLabels(ApiToken $record, string $kind, array $labels): string
    {
        $ids = $record->scopes->where('scope_kind', $kind)->pluck('scope_reference_id');

        if ($ids->isEmpty()) {
            return __('panel.api_clients.tokens.unrestricted');
        }

        return $ids->map(fn (int $id): string => $labels[$id] ?? "#{$id}")->implode(', ');
    }

    /** @return array<int, string> */
    private static function countryLabels(): array
    {
        return once(fn (): array => Country::query()->pluck('code', 'id')->all());
    }

    /** @return array<int, string> */
    private static function categoryLabels(): array
    {
        return once(fn (): array => ObjectType::query()->pluck('key', 'id')->all());
    }

    private function issueAction(): Action
    {
        return Action::make('issue')
            ->label(__('panel.api_clients.tokens.issue'))
            ->authorize(fn (): bool => (bool) auth()->user()?->can('update', $this->getOwnerRecord()))
            ->schema(self::scopeFields())
            ->action(function (array $data): void {
                $actor = Filament::auth()->user();

                if (! $actor instanceof User) {
                    return;
                }

                /** @var ApiClient $client */
                $client = $this->getOwnerRecord();

                $newAccessToken = app(ApiTokenService::class)->issue(
                    $client,
                    (string) $data['name'],
                    self::resourcesFromInput($data['resources'] ?? []),
                    self::intListFromInput($data['countries'] ?? []),
                    self::intListFromInput($data['categories'] ?? []),
                    self::nullableInt($data['rate_limit_per_minute'] ?? null),
                    self::nullableDate($data['expires_at'] ?? null),
                    $actor,
                );

                Notification::make()
                    ->title(__('panel.api_clients.tokens.issued'))
                    ->body(__('panel.api_clients.tokens.issued_body', ['token' => $newAccessToken->plainTextToken]))
                    ->success()
                    ->persistent()
                    ->send();
            });
    }

    private function editScopeAction(): Action
    {
        return Action::make('edit_scope')
            ->label(__('panel.api_clients.tokens.edit_scope'))
            ->authorize(fn (): bool => (bool) auth()->user()?->can('update', $this->getOwnerRecord()))
            ->visible(fn (ApiToken $record): bool => ! $record->isRevoked())
            ->schema(self::scopeFields())
            ->fillForm(fn (ApiToken $record): array => [
                'name' => $record->name,
                'resources' => $record->abilities,
                'countries' => $record->scopes->where('scope_kind', 'country')->pluck('scope_reference_id')->all(),
                'categories' => $record->scopes->where('scope_kind', 'category')->pluck('scope_reference_id')->all(),
                'rate_limit_per_minute' => $record->rate_limit_per_minute,
                'expires_at' => $record->expires_at,
            ])
            ->action(function (ApiToken $record, array $data): void {
                $actor = Filament::auth()->user();

                if (! $actor instanceof User) {
                    return;
                }

                app(ApiTokenService::class)->changeScope(
                    $record,
                    self::resourcesFromInput($data['resources'] ?? []),
                    self::intListFromInput($data['countries'] ?? []),
                    self::intListFromInput($data['categories'] ?? []),
                    $actor,
                );

                Notification::make()->title(__('panel.api_clients.tokens.applied'))->success()->send();
            });
    }

    private function revokeAction(): Action
    {
        return Action::make('revoke')
            ->label(__('panel.api_clients.tokens.revoke'))
            ->color('danger')
            ->authorize(fn (): bool => (bool) auth()->user()?->can('update', $this->getOwnerRecord()))
            ->visible(fn (ApiToken $record): bool => ! $record->isRevoked())
            ->requiresConfirmation()
            ->modalDescription(__('panel.api_clients.tokens.revoke_confirm'))
            ->action(function (ApiToken $record): void {
                $actor = Filament::auth()->user();

                if ($actor instanceof User) {
                    app(ApiTokenService::class)->revoke($record, $actor);
                }

                Notification::make()->title(__('panel.api_clients.tokens.applied'))->success()->send();
            });
    }

    /** @return array<Select|TextInput|DateTimePicker> */
    private static function scopeFields(): array
    {
        return [
            TextInput::make('name')
                ->label(__('panel.api_clients.tokens.name'))
                ->required()
                ->maxLength(255),

            Select::make('resources')
                ->label(__('panel.api_clients.tokens.resources'))
                ->multiple()
                ->options(fn (): array => collect(ApiResource::cases())
                    ->mapWithKeys(fn (ApiResource $resource): array => [$resource->value => __("panel.api_clients.tokens.resource.{$resource->value}")])
                    ->all())
                ->required(),

            Select::make('countries')
                ->label(__('panel.api_clients.tokens.countries'))
                ->multiple()
                ->options(fn (): array => Country::query()->orderBy('display_order')->pluck('code', 'id')->all())
                ->helperText(__('panel.api_clients.tokens.countries_hint')),

            Select::make('categories')
                ->label(__('panel.api_clients.tokens.categories'))
                ->multiple()
                ->options(fn (): array => ObjectType::query()->pluck('key', 'id')->all())
                ->helperText(__('panel.api_clients.tokens.categories_hint')),

            TextInput::make('rate_limit_per_minute')
                ->label(__('panel.api_clients.tokens.rate_limit'))
                ->numeric()
                ->minValue(1)
                ->helperText(__('panel.api_clients.tokens.rate_limit_hint')),

            DateTimePicker::make('expires_at')
                ->label(__('panel.api_clients.tokens.expires_at')),
        ];
    }

    /**
     * @return list<ApiResource>
     */
    private static function resourcesFromInput(mixed $input): array
    {
        return array_values(array_map(
            static fn (string $value): ApiResource => ApiResource::from($value),
            (array) $input,
        ));
    }

    /**
     * @return list<int>
     */
    private static function intListFromInput(mixed $input): array
    {
        return array_values(array_map(static fn (mixed $id): int => (int) $id, (array) $input));
    }

    private static function nullableInt(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    private static function nullableDate(mixed $value): ?Carbon
    {
        return $value === null || $value === '' ? null : Carbon::parse((string) $value);
    }
}
