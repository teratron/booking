<?php

declare(strict_types=1);

namespace App\Filament\Cabinet\Resources\Notifications\Tables;

use App\Models\Notification;
use App\Services\Notifications\NotificationDispatchService;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The signed-in account's own notification inbox — every row it has ever
 * received, read or not, with a single action per row toggling its read
 * state through {@see NotificationDispatchService}, the same service the
 * dispatch pipeline itself uses. There is no delete: an inbox is a record
 * of what an account was told, not a to-do list an owner clears.
 */
class NotificationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns(self::columns())
            ->defaultSort('created_at', 'desc')
            ->recordActions([self::toggleReadAction()])
            ->emptyStateHeading(__('panel.cabinet.notifications.empty'))
            ->emptyStateIcon(Heroicon::OutlinedBell);
    }

    /** @return list<TextColumn> */
    private static function columns(): array
    {
        return [
            TextColumn::make('title')
                ->label(__('panel.cabinet.notifications.columns.title'))
                ->weight(fn (Notification $record): string => $record->read_at === null ? 'bold' : 'normal'),

            TextColumn::make('body')
                ->label(__('panel.cabinet.notifications.columns.body'))
                ->limit(120)
                ->wrap(),

            TextColumn::make('created_at')
                ->label(__('panel.cabinet.notifications.columns.received_at'))
                ->dateTime(),

            TextColumn::make('read_at')
                ->label(__('panel.cabinet.notifications.columns.status'))
                ->badge()
                ->color(fn (Notification $record): string => $record->read_at === null ? 'warning' : 'success')
                ->formatStateUsing(fn (Notification $record): string => __(
                    'panel.cabinet.notifications.status.'.($record->read_at === null ? 'unread' : 'read')
                )),
        ];
    }

    private static function toggleReadAction(): Action
    {
        return Action::make('toggle_read')
            ->label(fn (Notification $record): string => __(
                'panel.cabinet.notifications.actions.'.($record->read_at === null ? 'mark_read' : 'mark_unread')
            ))
            ->icon(fn (Notification $record): string => $record->read_at === null ? 'heroicon-o-envelope-open' : 'heroicon-o-envelope')
            ->authorize(fn (Notification $record): bool => (bool) auth()->user()?->can('update', $record))
            ->action(function (Notification $record): void {
                $service = app(NotificationDispatchService::class);

                if ($record->read_at === null) {
                    $service->markAsRead($record);
                } else {
                    $service->markAsUnread($record);
                }
            });
    }
}
