<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seeds a representative launch subset of the object type registry — the
 * type registry is administrator-managed data, so growing it to the full
 * accommodation/dining/attraction spread the source requirements document
 * enumerates is a back-office data operation, never a migration or a code
 * change.
 */
final class ObjectTypeSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $now = now();

        $types = [
            ['key' => 'hotel', 'has_rooms' => true, 'has_availability_status' => true, 'structured_data_kind' => 'lodging', 'name' => ['en' => 'Hotel', 'ru' => 'Отель']],
            ['key' => 'guesthouse', 'has_rooms' => true, 'has_availability_status' => true, 'structured_data_kind' => 'lodging', 'name' => ['en' => 'Guesthouse', 'ru' => 'Гостевой дом']],
            ['key' => 'apartment', 'has_rooms' => false, 'has_availability_status' => true, 'structured_data_kind' => 'lodging', 'name' => ['en' => 'Apartment', 'ru' => 'Апартаменты']],
            ['key' => 'villa', 'has_rooms' => false, 'has_availability_status' => true, 'structured_data_kind' => 'lodging', 'name' => ['en' => 'Villa', 'ru' => 'Вилла']],
            ['key' => 'sanatorium', 'has_rooms' => true, 'has_availability_status' => true, 'structured_data_kind' => 'lodging', 'name' => ['en' => 'Sanatorium', 'ru' => 'Санаторий']],
            ['key' => 'restaurant', 'has_rooms' => false, 'has_availability_status' => false, 'structured_data_kind' => 'food', 'name' => ['en' => 'Restaurant', 'ru' => 'Ресторан']],
            ['key' => 'cafe', 'has_rooms' => false, 'has_availability_status' => false, 'structured_data_kind' => 'food', 'name' => ['en' => 'Cafe', 'ru' => 'Кафе']],
            ['key' => 'attraction', 'has_rooms' => false, 'has_availability_status' => false, 'structured_data_kind' => 'place', 'name' => ['en' => 'Attraction', 'ru' => 'Достопримечательность']],
        ];

        foreach ($types as $order => $type) {
            $typeId = DB::table('object_types')->insertGetId([
                'key' => $type['key'],
                'has_rooms' => $type['has_rooms'],
                'has_availability_status' => $type['has_availability_status'],
                'structured_data_kind' => $type['structured_data_kind'],
                'is_active' => true,
                'display_order' => $order + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($type['name'] as $locale => $name) {
                DB::table('object_type_translations')->insert([
                    'object_type_id' => $typeId,
                    'locale' => $locale,
                    'name' => $name,
                    'slug' => Str::slug($name),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
}
