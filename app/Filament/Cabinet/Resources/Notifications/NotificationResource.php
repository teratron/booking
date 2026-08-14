<?php

declare(strict_types=1);

namespace App\Filament\Cabinet\Resources\Notifications;

use App\Filament\Cabinet\Resources\Notifications\Pages\ListNotifications;
use App\Filament\Cabinet\Resources\Notifications\Tables\NotificationsTable;
use App\Filament\Cabinet\Support\CabinetResource;
use App\Models\Notification;
use App\Models\User;
use App\Services\Authorization\CabinetAccessResolver;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The owner cabinet's notification inbox — every `Notification` row
 * addressed to the signed-in account, independent of any particular
 * object. Unlike every other cabinet resource, this one is deliberately
 * NOT tenant-scoped: a notification belongs to the account, not to
 * whichever object happens to be the current tenant, so it must stay
 * reachable and identical no matter which object an owner has selected.
 */
class NotificationResource extends CabinetResource
{
    protected static ?string $model = Notification::class;

    protected static bool $isScopedToTenant = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBell;

    public static function getNavigationLabel(): string
    {
        return __('panel.cabinet.notifications.title');
    }

    public static function getModelLabel(): string
    {
        return __('panel.cabinet.notifications.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.cabinet.notifications.title');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * Scoped to the signed-in account's own `recipient_id` directly — not
     * tenant scoping (opted out above), and not
     * {@see CabinetAccessResolver} either,
     * since a notification has no owning object to resolve through at all.
     *
     * @return Builder<covariant \Illuminate\Database\Eloquent\Model>
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Filament::auth()->user();

        if (! $user instanceof User) {
            // Mirrors ScopedResource's own no-actor-resolves fallback: the
            // unnarrowed query would be the permissive one here.
            return $query->whereRaw('1 = 0');
        }

        return $query->where('recipient_id', $user->id);
    }

    public static function table(Table $table): Table
    {
        return NotificationsTable::configure(self::applyTableDefaults($table));
    }

    /** @return array<string, mixed> */
    public static function getPages(): array
    {
        return [
            'index' => ListNotifications::route('/'),
        ];
    }
}
