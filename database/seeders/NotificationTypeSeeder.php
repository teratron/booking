<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the fixed, code-known notification type set — administrators do
 * not invent new types through the UI, so unlike every other registry in
 * this task, there is no translations table: the back-office label is a
 * Filament translation key, and the actual per-language message text lives
 * in notification_templates.
 */
final class NotificationTypeSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $now = now();

        $types = [
            ['key' => 'placement_expiring', 'class' => 'transactional', 'channels' => ['email', 'inbox']],
            ['key' => 'package_expired', 'class' => 'transactional', 'channels' => ['email', 'inbox']],
            ['key' => 'moderation_approved', 'class' => 'transactional', 'channels' => ['email', 'inbox']],
            ['key' => 'moderation_rejected', 'class' => 'transactional', 'channels' => ['email', 'inbox']],
            ['key' => 'revision_requested', 'class' => 'transactional', 'channels' => ['email', 'inbox']],
            ['key' => 'information_out_of_date', 'class' => 'optional', 'channels' => ['inbox']],
            ['key' => 'confirm_availability_status', 'class' => 'optional', 'channels' => ['inbox']],
            ['key' => 'administration_message', 'class' => 'optional', 'channels' => ['inbox']],
            ['key' => 'object_status_changed', 'class' => 'transactional', 'channels' => ['email', 'inbox']],
            ['key' => 'system_message', 'class' => 'transactional', 'channels' => ['email', 'inbox']],
        ];

        foreach ($types as $type) {
            DB::table('notification_types')->insert([
                'key' => $type['key'],
                'class' => $type['class'],
                'default_channels' => json_encode($type['channels']),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
