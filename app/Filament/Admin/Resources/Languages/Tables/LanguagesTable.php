<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Languages\Tables;

use App\Exceptions\LanguageAdministrationRefusedException;
use App\Models\Language;
use App\Models\User;
use App\Services\Localization\LanguageAdministrationService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LanguagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label(__('panel.languages.columns.code'))
                    ->sortable(),

                TextColumn::make('short_label')
                    ->label(__('panel.languages.columns.short_label')),

                TextColumn::make('text_direction')
                    ->label(__('panel.languages.columns.text_direction'))
                    ->formatStateUsing(fn (string $state): string => __("panel.languages.text_direction.{$state}")),

                IconColumn::make('is_active')
                    ->label(__('panel.languages.columns.active'))
                    ->boolean(),

                IconColumn::make('is_primary')
                    ->label(__('panel.languages.columns.primary'))
                    ->boolean(),

                TextColumn::make('display_order')
                    ->label(__('panel.languages.columns.display_order')),
            ])
            ->recordActions([
                self::activateAction(),
                self::deactivateAction(),
                self::makePrimaryAction(),
            ])
            ->reorderable('display_order')
            ->afterReordering(fn (array $order) => app(LanguageAdministrationService::class)
                ->journalReorder(array_values($order), self::actor()))
            ->defaultSort('display_order');
    }

    private static function activateAction(): Action
    {
        return Action::make('activate')
            ->label(__('panel.languages.actions.activate'))
            ->icon('heroicon-o-eye')
            ->color('success')
            ->visible(fn (Language $record): bool => ! $record->is_active)
            ->action(function (Language $record): void {
                app(LanguageAdministrationService::class)->activate($record, self::actor());

                Notification::make()->title(__('panel.languages.notifications.activated'))->success()->send();
            });
    }

    private static function deactivateAction(): Action
    {
        return Action::make('deactivate')
            ->label(__('panel.languages.actions.deactivate'))
            ->icon('heroicon-o-eye-slash')
            ->color('gray')
            ->requiresConfirmation()
            ->visible(fn (Language $record): bool => $record->is_active && ! $record->is_primary)
            ->action(function (Language $record): void {
                try {
                    app(LanguageAdministrationService::class)->deactivate($record, self::actor());
                } catch (LanguageAdministrationRefusedException $exception) {
                    Notification::make()
                        ->title(__('panel.languages.notifications.deactivate_refused'))
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()->title(__('panel.languages.notifications.deactivated'))->success()->send();
            });
    }

    private static function makePrimaryAction(): Action
    {
        return Action::make('make_primary')
            ->label(__('panel.languages.actions.make_primary'))
            ->icon('heroicon-o-star')
            ->color('warning')
            ->requiresConfirmation()
            ->visible(fn (Language $record): bool => $record->is_active && ! $record->is_primary)
            ->action(function (Language $record): void {
                try {
                    app(LanguageAdministrationService::class)->makePrimary($record, self::actor());
                } catch (LanguageAdministrationRefusedException $exception) {
                    Notification::make()
                        ->title(__('panel.languages.notifications.make_primary_refused'))
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()->title(__('panel.languages.notifications.primary_changed'))->success()->send();
            });
    }

    private static function actor(): User
    {
        /** @var User $user */
        $user = Filament::auth()->user();

        return $user;
    }
}
