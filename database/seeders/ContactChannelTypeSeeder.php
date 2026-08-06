<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the contact channel registry. `link_template` turns a raw stored
 * value (a phone number, a handle) into an actionable deep link without the
 * owner ever entering a URI themselves — `{value}` is substituted at render
 * time by the channel-rendering service.
 */
final class ContactChannelTypeSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $now = now();

        $channels = [
            ['key' => 'phone', 'template' => 'tel:{value}', 'name' => ['en' => 'Phone', 'ru' => 'Телефон']],
            ['key' => 'whatsapp', 'template' => 'https://wa.me/{value}', 'name' => ['en' => 'WhatsApp', 'ru' => 'WhatsApp']],
            ['key' => 'viber', 'template' => 'viber://chat?number={value}', 'name' => ['en' => 'Viber', 'ru' => 'Viber']],
            ['key' => 'telegram', 'template' => 'https://t.me/{value}', 'name' => ['en' => 'Telegram', 'ru' => 'Telegram']],
            ['key' => 'email', 'template' => 'mailto:{value}', 'name' => ['en' => 'Email', 'ru' => 'Эл. почта']],
            ['key' => 'website', 'template' => '{value}', 'name' => ['en' => 'Website', 'ru' => 'Сайт']],
            ['key' => 'instagram', 'template' => 'https://instagram.com/{value}', 'name' => ['en' => 'Instagram', 'ru' => 'Instagram']],
            ['key' => 'facebook', 'template' => 'https://facebook.com/{value}', 'name' => ['en' => 'Facebook', 'ru' => 'Facebook']],
        ];

        foreach ($channels as $order => $channel) {
            $channelId = DB::table('contact_channel_types')->insertGetId([
                'key' => $channel['key'],
                'link_template' => $channel['template'],
                'is_active' => true,
                'display_order' => $order + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($channel['name'] as $locale => $name) {
                DB::table('contact_channel_type_translations')->insert([
                    'contact_channel_type_id' => $channelId,
                    'locale' => $locale,
                    'display_name' => $name,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
}
