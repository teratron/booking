<?php

declare(strict_types=1);

namespace App\Filament\Cabinet\Resources\Objects\Tables;

use App\Models\Object_;
use App\Models\User;
use App\Services\Cabinet\AvailabilityToggleService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Always exactly one row — Filament's own tenancy scopes this resource's
 * query to the current tenant object's primary key. Still a real table
 * rather than a bespoke page: it is what lets `EditAction` produce the edit
 * URL every other cabinet screen's "Edit object" link points at, and it
 * gives an owner a landing state to see even before that link is clicked.
 */
class ObjectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns(self::columns())
            ->recordActions([self::toggleAvailabilityAction(), EditAction::make()]);
    }

    /** @return array<TextColumn> */
    private static function columns(): array
    {
        return [
            TextColumn::make('name')
                ->label(__('panel.objects.columns.name'))
                ->getStateUsing(fn (Object_ $record): string => $record->name ?? "#{$record->id}"),

            TextColumn::make('objectType.key')
                ->label(__('panel.objects.columns.type'))
                ->badge(),

            TextColumn::make('status')
                ->label(__('panel.objects.columns.status'))
                ->badge()
                ->formatStateUsing(fn (string $state): string => __("panel.objects.status.{$state}")),

            TextColumn::make('moderation_status')
                ->label(__('panel.objects.columns.moderation_status'))
                ->badge()
                ->placeholder('—')
                ->formatStateUsing(fn (?string $state): string => $state === null ? '—' : __("panel.objects.moderation.{$state}")),

            TextColumn::make('availability_status')
                ->label(__('panel.objects.columns.availability'))
                ->badge()
                ->formatStateUsing(fn (string $state): string => __("panel.objects.availability.{$state}")),
        ];
    }

    /**
     * The compact one-tap availability switch, reachable from the object
     * list without opening the edit form — no confirmation modal, since a
     * confirmed tap is the entire point of the switch. Mirrors the same
     * action the cabinet dashboard's header mounts, both writing through
     * the one narrow toggle path.
     */
    private static function toggleAvailabilityAction(): Action
    {
        return Action::make('toggle_availability')
            ->label(fn (Object_ $record): string => $record->availability_status === 'available'
                ? __('panel.cabinet.availability.mark_unavailable')
                : __('panel.cabinet.availability.mark_available'))
            ->color(fn (Object_ $record): string => $record->availability_status === 'available' ? 'danger' : 'success')
            ->icon(fn (Object_ $record): string => $record->availability_status === 'available' ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
            ->authorize(fn (Object_ $record): bool => (bool) auth()->user()?->can('update', $record))
            ->action(function (Object_ $record): void {
                $actor = Filament::auth()->user();

                if (! $actor instanceof User) {
                    return;
                }

                app(AvailabilityToggleService::class)->toggle($record, $actor);

                Notification::make()->title(__('panel.cabinet.availability.toggled'))->success()->send();
            });
    }
}
