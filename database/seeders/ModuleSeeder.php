<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the launch module registry, including its dependency edges, since
 * `booking` enabled while `guest_accounts` is off must resolve to inactive
 * rather than a broken half-capability. No launch module declares a
 * conflict.
 */
final class ModuleSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $now = now();

        $modules = [
            [
                'key' => 'reviews', 'default_state' => 'enabled',
                'scopable_levels' => ['portal', 'country', 'category', 'object'],
                'name' => ['en' => 'Reviews', 'ru' => 'Отзывы'],
                'description' => ['en' => 'Visitor reviews on object pages.', 'ru' => 'Отзывы посетителей на страницах объектов.'],
            ],
            [
                'key' => 'guest_accounts', 'default_state' => 'disabled',
                'scopable_levels' => ['portal'],
                'name' => ['en' => 'Guest accounts', 'ru' => 'Аккаунты гостей'],
                'description' => ['en' => 'Visitor registration and profiles.', 'ru' => 'Регистрация и профили посетителей.'],
            ],
            [
                'key' => 'booking', 'default_state' => 'disabled',
                'scopable_levels' => ['portal', 'country', 'category', 'owner', 'object'],
                'name' => ['en' => 'Booking', 'ru' => 'Бронирование'],
                'description' => ['en' => 'Dated room enquiries and owner confirmation.', 'ru' => 'Заявки на бронирование номеров с подтверждением от владельца.'],
                'dependsOn' => ['guest_accounts'],
            ],
            [
                'key' => 'payment', 'default_state' => 'disabled',
                'scopable_levels' => ['portal', 'country'],
                'name' => ['en' => 'Payment', 'ru' => 'Оплата'],
                'description' => ['en' => 'Prepaid checkout on confirmed bookings.', 'ru' => 'Предоплата при подтверждённом бронировании.'],
                'dependsOn' => ['booking'],
            ],
            [
                'key' => 'api', 'default_state' => 'disabled',
                'scopable_levels' => ['portal'],
                'name' => ['en' => 'Public API', 'ru' => 'Публичное API'],
                'description' => ['en' => 'Read-only, tokened REST access to published catalog data.', 'ru' => 'Публичный доступ на чтение к опубликованным данным каталога по токену.'],
            ],
        ];

        $moduleIdByKey = [];

        foreach ($modules as $module) {
            $moduleId = DB::table('modules')->insertGetId([
                'key' => $module['key'],
                'default_state' => $module['default_state'],
                'scopable_levels' => json_encode($module['scopable_levels']),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $moduleIdByKey[$module['key']] = $moduleId;

            foreach (['en', 'ru'] as $locale) {
                DB::table('module_translations')->insert([
                    'module_id' => $moduleId,
                    'locale' => $locale,
                    'display_name' => $module['name'][$locale],
                    'description' => $module['description'][$locale],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        foreach ($modules as $module) {
            foreach ($module['dependsOn'] ?? [] as $dependency) {
                DB::table('module_dependencies')->insert([
                    'module_id' => $moduleIdByKey[$module['key']],
                    'depends_on_module_id' => $moduleIdByKey[$dependency],
                ]);
            }
        }
    }
}
