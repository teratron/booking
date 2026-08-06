<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds each launch country's own territory-level vocabulary. The ladders
 * genuinely differ per country, which is why this is a per-country registry
 * rather than a fixed depth list — depth_rank orders the vocabulary for
 * breadcrumb labelling only; it does not constrain the actual tree shape.
 */
final class TerritoryLevelSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $now = now();
        $countryIdByCode = DB::table('countries')->pluck('id', 'code');

        $ladders = [
            'MD' => [
                ['singular' => ['en' => 'District', 'ru' => 'Район'], 'plural' => ['en' => 'Districts', 'ru' => 'Районы']],
                ['singular' => ['en' => 'Municipality', 'ru' => 'Муниципий'], 'plural' => ['en' => 'Municipalities', 'ru' => 'Муниципии']],
                ['singular' => ['en' => 'Town', 'ru' => 'Город'], 'plural' => ['en' => 'Towns', 'ru' => 'Города']],
                ['singular' => ['en' => 'Village', 'ru' => 'Село'], 'plural' => ['en' => 'Villages', 'ru' => 'Сёла']],
            ],
            'UA' => [
                ['singular' => ['en' => 'Oblast', 'ru' => 'Область'], 'plural' => ['en' => 'Oblasts', 'ru' => 'Области']],
                ['singular' => ['en' => 'Raion', 'ru' => 'Район'], 'plural' => ['en' => 'Raions', 'ru' => 'Районы']],
                ['singular' => ['en' => 'City / Resort', 'ru' => 'Город / Курорт'], 'plural' => ['en' => 'Cities / Resorts', 'ru' => 'Города / Курорты']],
                ['singular' => ['en' => 'Village', 'ru' => 'Село'], 'plural' => ['en' => 'Villages', 'ru' => 'Сёла']],
            ],
            'GE' => [
                ['singular' => ['en' => 'Region', 'ru' => 'Регион'], 'plural' => ['en' => 'Regions', 'ru' => 'Регионы']],
                ['singular' => ['en' => 'Municipality', 'ru' => 'Муниципалитет'], 'plural' => ['en' => 'Municipalities', 'ru' => 'Муниципалитеты']],
                ['singular' => ['en' => 'City', 'ru' => 'Город'], 'plural' => ['en' => 'Cities', 'ru' => 'Города']],
                ['singular' => ['en' => 'Resort', 'ru' => 'Курорт'], 'plural' => ['en' => 'Resorts', 'ru' => 'Курорты']],
            ],
        ];

        foreach ($ladders as $countryCode => $levels) {
            foreach ($levels as $depthRank => $level) {
                $levelId = DB::table('territory_levels')->insertGetId([
                    'country_id' => $countryIdByCode[$countryCode],
                    'depth_rank' => $depthRank + 1,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                foreach (['en', 'ru'] as $locale) {
                    DB::table('territory_level_translations')->insert([
                        'territory_level_id' => $levelId,
                        'locale' => $locale,
                        'singular_name' => $level['singular'][$locale],
                        'plural_name' => $level['plural'][$locale],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }
}
