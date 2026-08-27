<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Staff\RelationManagers;

use App\Exceptions\UnrevocableGrantException;
use App\Filament\Admin\Resources\Staff\StaffResource;
use App\Models\Country;
use App\Models\ObjectType;
use App\Models\RoleScope;
use App\Models\Territory;
use App\Models\User;
use App\Services\Authorization\RoleGrantPresenter;
use App\Services\Authorization\RoleGrantService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Override;
use Spatie\Permission\Models\Role;

/**
 * Every role grant a staff account holds, active and revoked alike — the
 * "what can this account do" read-back the specification requires, and the
 * one screen that grants and revokes a role. Both mutations go through
 * {@see RoleGrantService} rather than a bare Eloquent write against
 * `RoleScope`: the service is what keeps the Spatie assignment and the
 * scope row from drifting apart, and what carries the
 * last-remaining-chief-administrator guard.
 */
class RoleGrantsRelationManager extends RelationManager
{
    protected static string $relationship = 'roleScopes';

    #[Override]
    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('panel.staff.grants.title');
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
            ->recordTitleAttribute('id')
            ->modifyQueryUsing(self::baseQuery(...))
            ->columns(self::columns())
            ->headerActions([$this->grantAction()])
            ->recordActions([$this->revokeAction()])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * @param  Builder<RoleScope>  $query
     * @return Builder<RoleScope>
     */
    private static function baseQuery(Builder $query): Builder
    {
        return $query->with(['role', 'granter', 'revoker']);
    }

    /** @return array<TextColumn> */
    private static function columns(): array
    {
        $presenter = app(RoleGrantPresenter::class);

        return [
            TextColumn::make('role.name')
                ->label(__('panel.staff.grants.columns.role'))
                ->getStateUsing(fn (RoleScope $record): string => $presenter->roleName(
                    $record->role_id,
                    $record->role instanceof Role ? $record->role->name : (string) $record->role_id,
                )),

            TextColumn::make('scope_kind')
                ->label(__('panel.staff.grants.columns.scope'))
                ->getStateUsing(fn (RoleScope $record): string => $presenter->scopeDescription(
                    $record->scope_kind,
                    $record->scope_reference_id,
                )),

            TextColumn::make('granter.name')
                ->label(__('panel.staff.grants.columns.granted_by')),

            TextColumn::make('granted_at')
                ->label(__('panel.staff.grants.columns.granted_at'))
                ->dateTime(),

            TextColumn::make('status')
                ->label(__('panel.staff.grants.columns.status'))
                ->getStateUsing(fn (RoleScope $record): string => $record->revoked_at === null
                    ? __('panel.staff.grants.status.active')
                    : __('panel.staff.grants.status.revoked'))
                ->badge()
                ->color(fn (RoleScope $record): string => $record->revoked_at === null ? 'success' : 'gray'),

            TextColumn::make('revoked_at')
                ->label(__('panel.staff.grants.columns.revoked_at'))
                ->dateTime()
                ->placeholder('—')
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    private function grantAction(): Action
    {
        return Action::make('grant')
            ->label(__('panel.staff.grants.actions.grant'))
            ->authorize(fn (): bool => (bool) Filament::auth()->user()?->hasRole('chief_administrator'))
            ->schema([
                Select::make('role_id')
                    ->label(__('panel.staff.grants.form.role'))
                    ->options(fn (): array => StaffResource::grantableRoleOptions())
                    ->required()
                    ->searchable(),

                Select::make('scope_kind')
                    ->label(__('panel.staff.grants.form.scope_kind'))
                    ->options([
                        'none' => __('panel.staff.grants.scope_kind.none'),
                        'country' => __('panel.staff.grants.scope_kind.country'),
                        'territory' => __('panel.staff.grants.scope_kind.territory'),
                        'category' => __('panel.staff.grants.scope_kind.category'),
                    ])
                    ->default('none')
                    ->live()
                    ->required(),

                // A plain eager ->options() list works for country and
                // category — both small, fixed registries — but not for
                // territory: the volume seeder alone puts thousands of rows
                // in that table, and loading all of them into one dropdown
                // is exactly the pattern SearchableModelSelect exists to
                // replace elsewhere in this panel. One searchable field
                // covers all three by branching its result source (and its
                // selected-option label, for whichever kind was picked
                // before the field was reopened) on the sibling scope_kind
                // field rather than swapping components.
                Select::make('scope_reference_id')
                    ->label(__('panel.staff.grants.form.scope_reference'))
                    ->visible(fn (Get $get): bool => $get('scope_kind') !== 'none')
                    ->required(fn (Get $get): bool => $get('scope_kind') !== 'none')
                    ->searchable()
                    ->getSearchResultsUsing(fn (Get $get, string $search): array => match ($get('scope_kind')) {
                        'country' => Country::query()
                            ->where('code', 'ilike', "%{$search}%")
                            ->orderBy('display_order')
                            ->limit(50)
                            ->pluck('code', 'id')
                            ->all(),
                        'territory' => Territory::query()
                            ->where('is_active', true)
                            ->whereHas('translations', fn (Builder $query) => $query->where('name', 'ilike', "%{$search}%"))
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(fn (Territory $territory): array => [$territory->id => $territory->name ?? "#{$territory->id}"])
                            ->all(),
                        'category' => ObjectType::query()
                            ->whereHas('translations', fn (Builder $query) => $query->where('name', 'ilike', "%{$search}%"))
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(fn (ObjectType $type): array => [$type->id => $type->name ?? "#{$type->id}"])
                            ->all(),
                        default => [],
                    })
                    ->getOptionLabelUsing(fn (int|string|null $value, Get $get): ?string => match ($get('scope_kind')) {
                        'country' => Country::query()->find($value)?->code,
                        'territory' => Territory::query()->find($value)?->name,
                        'category' => ObjectType::query()->find($value)?->name,
                        default => null,
                    }),
            ])
            ->action(function (array $data): void {
                $actor = Filament::auth()->user();

                if (! $actor instanceof User) {
                    return;
                }

                $role = Role::query()->find($data['role_id']);

                if (! $role instanceof Role) {
                    return;
                }

                /** @var User $staff */
                $staff = $this->getOwnerRecord();

                app(RoleGrantService::class)->grantRole(
                    $staff,
                    $role->name,
                    $actor,
                    $data['scope_kind'],
                    $data['scope_kind'] === 'none' ? null : (int) $data['scope_reference_id'],
                );

                Notification::make()->title(__('panel.staff.grants.applied'))->success()->send();
            });
    }

    private function revokeAction(): Action
    {
        return Action::make('revoke')
            ->label(__('panel.staff.grants.actions.revoke'))
            ->color('danger')
            ->visible(fn (RoleScope $record): bool => $record->revoked_at === null)
            ->authorize(fn (): bool => (bool) Filament::auth()->user()?->hasRole('chief_administrator'))
            ->requiresConfirmation()
            ->modalDescription(__('panel.staff.grants.revoke_confirm'))
            ->action(function (RoleScope $record): void {
                $actor = Filament::auth()->user();
                $role = Role::query()->find($record->role_id);

                if (! $actor instanceof User || ! $role instanceof Role) {
                    return;
                }

                /** @var User $staff */
                $staff = $this->getOwnerRecord();

                try {
                    app(RoleGrantService::class)->revokeRole($staff, $role->name, $actor);
                } catch (UnrevocableGrantException) {
                    Notification::make()
                        ->danger()
                        ->title(__('panel.staff.grants.revoke_refused'))
                        ->send();

                    return;
                }

                Notification::make()->title(__('panel.staff.grants.applied'))->success()->send();
            });
    }
}
