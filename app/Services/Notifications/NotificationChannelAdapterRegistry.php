<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Services\Notifications\Channels\NotificationChannelAdapter;
use InvalidArgumentException;

/**
 * Resolves a channel key to its adapter, reading `config/notifications.php`
 * rather than a hardcoded match — the registration point a new channel adds
 * itself to.
 */
final class NotificationChannelAdapterRegistry
{
    public function forKey(string $channelKey): NotificationChannelAdapter
    {
        /** @var array<string, class-string<NotificationChannelAdapter>> $adapters */
        $adapters = config('notifications.adapters', []);
        $adapterClass = $adapters[$channelKey] ?? null;

        if ($adapterClass === null) {
            throw new InvalidArgumentException("No adapter registered for notification channel [{$channelKey}].");
        }

        return app($adapterClass);
    }
}
