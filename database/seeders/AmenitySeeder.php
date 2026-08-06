<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the amenity groups and their amenities, plus which object types
 * each group applies to — the pivot the owner-cabinet service-selection
 * screen filters against, so a restaurant is never offered room amenities.
 */
final class AmenitySeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $now = now();
        $accommodation = ['hotel', 'guesthouse', 'apartment', 'villa', 'sanatorium'];
        $dining = ['restaurant', 'cafe'];

        $groups = [
            'general' => [
                'name' => ['en' => 'General', 'ru' => 'Общее'],
                'appliesTo' => [...$accommodation, ...$dining, 'attraction'],
                'amenities' => [
                    ['key' => 'wifi', 'filterable' => true, 'name' => ['en' => 'Wi-Fi', 'ru' => 'Wi-Fi']],
                    ['key' => 'parking', 'filterable' => true, 'name' => ['en' => 'Parking', 'ru' => 'Парковка']],
                ],
            ],
            'grounds' => [
                'name' => ['en' => 'Grounds', 'ru' => 'Территория'],
                'appliesTo' => $accommodation,
                'amenities' => [
                    ['key' => 'garden', 'filterable' => false, 'name' => ['en' => 'Garden', 'ru' => 'Сад']],
                    ['key' => 'terrace', 'filterable' => false, 'name' => ['en' => 'Terrace', 'ru' => 'Терраса']],
                ],
            ],
            'catering' => [
                'name' => ['en' => 'Catering', 'ru' => 'Питание'],
                'appliesTo' => [...$accommodation, ...$dining],
                'amenities' => [
                    ['key' => 'breakfast_included', 'filterable' => true, 'name' => ['en' => 'Breakfast included', 'ru' => 'Завтрак включён']],
                    ['key' => 'restaurant_on_site', 'filterable' => false, 'name' => ['en' => 'Restaurant on site', 'ru' => 'Ресторан на территории']],
                ],
            ],
            'rooms' => [
                'name' => ['en' => 'Rooms', 'ru' => 'Номера'],
                'appliesTo' => ['hotel', 'guesthouse', 'sanatorium'],
                'amenities' => [
                    ['key' => 'air_conditioning', 'filterable' => true, 'name' => ['en' => 'Air conditioning', 'ru' => 'Кондиционер']],
                    ['key' => 'private_bathroom', 'filterable' => true, 'name' => ['en' => 'Private bathroom', 'ru' => 'Собственная ванная']],
                ],
            ],
            'pool_and_spa' => [
                'name' => ['en' => 'Pool & Spa', 'ru' => 'Бассейн и СПА'],
                'appliesTo' => ['hotel', 'villa', 'sanatorium'],
                'amenities' => [
                    ['key' => 'swimming_pool', 'filterable' => true, 'name' => ['en' => 'Swimming pool', 'ru' => 'Бассейн']],
                    ['key' => 'spa_treatments', 'filterable' => true, 'name' => ['en' => 'Spa treatments', 'ru' => 'СПА-процедуры']],
                ],
            ],
            'family' => [
                'name' => ['en' => 'Family', 'ru' => 'Для семьи'],
                'appliesTo' => [...$accommodation, ...$dining],
                'amenities' => [
                    ['key' => 'kids_area', 'filterable' => true, 'name' => ['en' => "Kids' area", 'ru' => 'Детская площадка']],
                    ['key' => 'crib_available', 'filterable' => false, 'name' => ['en' => 'Crib available', 'ru' => 'Детская кроватка']],
                ],
            ],
            'business' => [
                'name' => ['en' => 'Business', 'ru' => 'Для бизнеса'],
                'appliesTo' => ['hotel', 'sanatorium'],
                'amenities' => [
                    ['key' => 'conference_room', 'filterable' => true, 'name' => ['en' => 'Conference room', 'ru' => 'Конференц-зал']],
                    ['key' => 'business_center', 'filterable' => false, 'name' => ['en' => 'Business centre', 'ru' => 'Бизнес-центр']],
                ],
            ],
            'transport' => [
                'name' => ['en' => 'Transport', 'ru' => 'Транспорт'],
                'appliesTo' => [...$accommodation, ...$dining, 'attraction'],
                'amenities' => [
                    ['key' => 'airport_shuttle', 'filterable' => true, 'name' => ['en' => 'Airport shuttle', 'ru' => 'Трансфер из аэропорта']],
                    ['key' => 'ev_charging', 'filterable' => false, 'name' => ['en' => 'EV charging', 'ru' => 'Зарядка для электромобилей']],
                ],
            ],
            'accessibility' => [
                'name' => ['en' => 'Accessibility', 'ru' => 'Доступность'],
                'appliesTo' => [...$accommodation, ...$dining, 'attraction'],
                'amenities' => [
                    ['key' => 'wheelchair_accessible', 'filterable' => true, 'name' => ['en' => 'Wheelchair accessible', 'ru' => 'Доступно для колясок']],
                    ['key' => 'elevator', 'filterable' => false, 'name' => ['en' => 'Elevator', 'ru' => 'Лифт']],
                ],
            ],
            'pets' => [
                'name' => ['en' => 'Pets', 'ru' => 'Питомцы'],
                'appliesTo' => $accommodation,
                'amenities' => [
                    ['key' => 'pets_allowed', 'filterable' => true, 'name' => ['en' => 'Pets allowed', 'ru' => 'Можно с животными']],
                ],
            ],
        ];

        $objectTypeIdByKey = DB::table('object_types')->pluck('id', 'key');

        foreach (array_values($groups) as $order => $group) {
            $groupId = DB::table('amenity_groups')->insertGetId([
                'is_active' => true,
                'display_order' => $order + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($group['name'] as $locale => $name) {
                DB::table('amenity_group_translations')->insert([
                    'amenity_group_id' => $groupId,
                    'locale' => $locale,
                    'name' => $name,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            foreach ($group['appliesTo'] as $typeKey) {
                DB::table('amenity_group_object_type')->insert([
                    'amenity_group_id' => $groupId,
                    'object_type_id' => $objectTypeIdByKey[$typeKey],
                ]);
            }

            foreach ($group['amenities'] as $amenityOrder => $amenity) {
                $amenityId = DB::table('amenities')->insertGetId([
                    'amenity_group_id' => $groupId,
                    'is_filterable' => $amenity['filterable'],
                    'is_active' => true,
                    'display_order' => $amenityOrder + 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                foreach ($amenity['name'] as $locale => $name) {
                    DB::table('amenity_translations')->insert([
                        'amenity_id' => $amenityId,
                        'locale' => $locale,
                        'name' => $name,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }
}
