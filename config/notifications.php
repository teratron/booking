<?php

declare(strict_types=1);

use App\Services\Notifications\Channels\EmailChannelAdapter;
use App\Services\Notifications\Channels\InboxChannelAdapter;

return [

    /*
    |--------------------------------------------------------------------------
    | Channel Adapters
    |--------------------------------------------------------------------------
    |
    | Maps a notification_channels.key to the class delivering it. Adding a
    | channel (Telegram, Viber) is a notification_channels row plus a class
    | implementing NotificationChannelAdapter registered here — never a
    | change to the notification model or the dispatch pipeline.
    |
    */

    'adapters' => [
        'inbox' => InboxChannelAdapter::class,
        'email' => EmailChannelAdapter::class,
    ],

];
