<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the banner inventory registry. Without these rows every
 * BannerSelectionService::forSlot() call resolves to null regardless of how
 * many banners exist — resolveSlot() looks a slot up by key and never
 * creates one, so a fresh install's advertising inventory was empty on
 * every page, home included, not only the geographic pages this seeder's
 * newer entries were added to close.
 */
final class BannerSlotSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $now = now();

        $slots = [
            ['key' => 'home-top', 'surfaces' => ['home'], 'name' => ['en' => 'Home — top', 'ru' => 'Главная — верх']],
            ['key' => 'home-mid', 'surfaces' => ['home'], 'name' => ['en' => 'Home — middle', 'ru' => 'Главная — середина']],
            ['key' => 'home-bottom', 'surfaces' => ['home'], 'name' => ['en' => 'Home — bottom', 'ru' => 'Главная — низ']],
            ['key' => 'home-partners', 'surfaces' => ['home'], 'name' => ['en' => 'Home — partner strip', 'ru' => 'Главная — партнёры']],
            // Geographic inventory (§24): scoped to whichever territory and
            // category the rendering page resolves, per BannerSelectionService's
            // own targeting contract — a resort page requesting these slots
            // only ever surfaces a banner targeted at that resort or broader.
            ['key' => 'territory-top', 'surfaces' => ['country', 'region', 'city', 'resort'], 'name' => ['en' => 'Territory — after description', 'ru' => 'Территория — после описания']],
            ['key' => 'territory-mid', 'surfaces' => ['country', 'region', 'city', 'resort'], 'name' => ['en' => 'Territory — between catalog blocks', 'ru' => 'Территория — между блоками каталога']],
            ['key' => 'territory-bottom', 'surfaces' => ['country', 'region', 'city', 'resort'], 'name' => ['en' => 'Territory — before news', 'ru' => 'Территория — перед новостями']],
            ['key' => 'typed-catalog-top', 'surfaces' => ['category'], 'name' => ['en' => 'Typed catalog — top', 'ru' => 'Каталог по типу — верх']],
            ['key' => 'catalog-top', 'surfaces' => ['category'], 'name' => ['en' => 'Catalog search — top', 'ru' => 'Поиск по каталогу — верх']],
            ['key' => 'object-top', 'surfaces' => ['object'], 'name' => ['en' => 'Object page — top', 'ru' => 'Страница объекта — верх']],
        ];

        foreach ($slots as $slot) {
            $slotId = DB::table('banner_slots')->insertGetId([
                'key' => $slot['key'],
                'surfaces' => json_encode($slot['surfaces']),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($slot['name'] as $locale => $name) {
                DB::table('banner_slot_translations')->insert([
                    'banner_slot_id' => $slotId,
                    'locale' => $locale,
                    'name' => $name,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
}
