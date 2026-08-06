<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Modules\Tables;

use App\Exceptions\ModuleDependencyException;
use App\Models\Country;
use App\Models\Module;
use App\Models\User;
use App\Services\Modules\ModuleAdministrator;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Lists every registry entry with its effective portal state, its dependency
 * graph, and the toggles that change it.
 *
 * Both toggles confirm before they run and both name their blast radius as a
 * counted number, because a portal- or country-scope change alters what
 * thousands of pages render and "are you sure?" gives an administrator nothing
 * to check the intent against.
 */
class ModulesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key')
                    ->label(__('panel.modules.columns.key'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('display_name')
                    ->label(__('panel.modules.columns.name'))
                    ->getStateUsing(fn (Module $record): string => $record->display_name ?? $record->key),

                TextColumn::make('default_state')
                    ->label(__('panel.modules.columns.default_state'))
                    ->badge(),

                IconColumn::make('effective_portal_state')
                    ->label(__('panel.modules.columns.effective'))
                    ->boolean()
                    ->getStateUsing(fn (Module $record): bool => app(ModuleAdministrator::class)
                        ->effectiveState($record, 'portal', null)),

                TextColumn::make('dependencies.key')
                    ->label(__('panel.modules.columns.dependencies'))
                    ->badge()
                    ->placeholder('—'),

                IconColumn::make('is_active')
                    ->label(__('panel.modules.columns.registered'))
                    ->boolean(),
            ])
            ->recordActions([
                ActionGroup::make([
                    self::portalToggle(true),
                    self::portalToggle(false),
                    self::countryToggle(),
                ]),
            ]);
    }

    private static function portalToggle(bool $enable): Action
    {
        $name = $enable ? 'enable_portal' : 'disable_portal';

        return Action::make($name)
            ->label(__("panel.modules.actions.{$name}"))
            ->icon($enable ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
            ->color($enable ? 'success' : 'danger')
            ->requiresConfirmation()
            ->modalDescription(fn (Module $record): string => __('panel.modules.confirm', [
                'module' => $record->display_name ?? $record->key,
                'scope' => __('panel.modules.scope.portal'),
                'count' => app(ModuleAdministrator::class)->blastRadius('portal', null),
            ]))
            ->visible(fn (): bool => self::mayManage())
            ->action(fn (Module $record) => self::apply($record, 'portal', null, $enable));
    }

    private static function countryToggle(): Action
    {
        return Action::make('set_for_country')
            ->label(__('panel.modules.actions.set_for_country'))
            ->icon('heroicon-o-globe-alt')
            ->schema([
                Select::make('country_id')
                    ->label(__('panel.modules.fields.country'))
                    ->options(fn (): array => Country::query()->orderBy('display_order')->pluck('code', 'id')->all())
                    ->required()
                    ->live(),
                Select::make('state')
                    ->label(__('panel.modules.fields.state'))
                    ->options([
                        'enabled' => __('panel.modules.state.enabled'),
                        'disabled' => __('panel.modules.state.disabled'),
                    ])
                    ->required(),
            ])
            ->requiresConfirmation()
            ->modalDescription(fn (Module $record, array $data): string => __('panel.modules.confirm', [
                'module' => $record->display_name ?? $record->key,
                'scope' => __('panel.modules.scope.country'),
                'count' => app(ModuleAdministrator::class)->blastRadius('country', (int) ($data['country_id'] ?? 0)),
            ]))
            ->visible(fn (): bool => self::mayManage())
            ->action(fn (Module $record, array $data) => self::apply(
                $record,
                'country',
                (int) $data['country_id'],
                $data['state'] === 'enabled',
            ));
    }

    private static function apply(Module $record, string $scopeLevel, ?int $reference, bool $enabled): void
    {
        $actor = Filament::auth()->user();

        if (! $actor instanceof User) {
            return;
        }

        try {
            app(ModuleAdministrator::class)->setState($record, $scopeLevel, $reference, $enabled, $actor);
        } catch (ModuleDependencyException $exception) {
            Notification::make()
                ->title(__('panel.modules.refused'))
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        Notification::make()->title(__('panel.modules.applied'))->success()->send();
    }

    private static function mayManage(): bool
    {
        $actor = Filament::auth()->user();

        return $actor instanceof User && $actor->can('settings_management');
    }
}
