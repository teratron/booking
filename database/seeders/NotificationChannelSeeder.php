<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the notification delivery channel registry — adding a channel is a
 * registry entry plus a dispatch adapter, never a change to the
 * notification model itself.
 */
final class NotificationChannelSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $now = now();

        $channels = [
            ['key' => 'inbox', 'name' => ['en' => 'Inbox', 'ru' => 'Входящие']],
            ['key' => 'email', 'name' => ['en' => 'Email', 'ru' => 'Эл. почта']],
            ['key' => 'telegram', 'name' => ['en' => 'Telegram', 'ru' => 'Telegram']],
            ['key' => 'viber', 'name' => ['en' => 'Viber', 'ru' => 'Viber']],
        ];

        foreach ($channels as $channel) {
            $channelId = DB::table('notification_channels')->insertGetId([
                'key' => $channel['key'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($channel['name'] as $locale => $name) {
                DB::table('notification_channel_translations')->insert([
                    'notification_channel_id' => $channelId,
                    'locale' => $locale,
                    'name' => $name,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
}
