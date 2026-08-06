<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the language registry per the phased-activation design: the launch
 * set (English, Russian) ships active, and the three launch countries' own
 * primary languages (Romanian, Ukrainian, Georgian) ship present but
 * inactive — a normal, resolvable state, not a gap to fill before launch.
 * Every later registry's translations only need to cover the active set.
 */
final class LanguageSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $now = now();

        DB::table('languages')->insert([
            [
                'code' => 'en', 'short_label' => 'EN', 'text_direction' => 'ltr',
                'is_active' => true, 'is_primary' => true, 'display_order' => 1,
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'code' => 'ru', 'short_label' => 'RU', 'text_direction' => 'ltr',
                'is_active' => true, 'is_primary' => false, 'display_order' => 2,
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'code' => 'ro', 'short_label' => 'RO', 'text_direction' => 'ltr',
                'is_active' => false, 'is_primary' => false, 'display_order' => 3,
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'code' => 'uk', 'short_label' => 'UK', 'text_direction' => 'ltr',
                'is_active' => false, 'is_primary' => false, 'display_order' => 4,
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'code' => 'ka', 'short_label' => 'KA', 'text_direction' => 'ltr',
                'is_active' => false, 'is_primary' => false, 'display_order' => 5,
                'created_at' => $now, 'updated_at' => $now,
            ],
        ]);
    }
}
