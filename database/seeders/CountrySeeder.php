<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the three launch countries. Each references its own primary
 * language even though that language ships inactive (Romanian, Ukrainian,
 * Georgian) — the foreign key targets `languages.code`, not the active
 * flag, so this is a normal insert, not a constraint violation.
 */
final class CountrySeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $now = now();
        $languageIdByCode = DB::table('languages')->pluck('id', 'code');

        $countries = [
            ['code' => 'MD', 'currency' => 'MDL', 'phone_code' => '+373', 'primary' => 'ro', 'order' => 1, 'name' => ['en' => 'Moldova', 'ru' => 'Молдова']],
            ['code' => 'UA', 'currency' => 'UAH', 'phone_code' => '+380', 'primary' => 'uk', 'order' => 2, 'name' => ['en' => 'Ukraine', 'ru' => 'Украина']],
            ['code' => 'GE', 'currency' => 'GEL', 'phone_code' => '+995', 'primary' => 'ka', 'order' => 3, 'name' => ['en' => 'Georgia', 'ru' => 'Грузия']],
        ];

        foreach ($countries as $country) {
            $countryId = DB::table('countries')->insertGetId([
                'code' => $country['code'],
                'currency' => $country['currency'],
                'phone_code' => $country['phone_code'],
                'primary_language_id' => $languageIdByCode[$country['primary']],
                'is_active' => true,
                'display_order' => $country['order'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($country['name'] as $locale => $name) {
                DB::table('country_translations')->insert([
                    'country_id' => $countryId,
                    'locale' => $locale,
                    'name' => $name,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
}
