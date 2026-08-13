<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the four structural placement ranks — the count and order are
 * fixed; labels, colours, and packages are administrator-editable data —
 * and one launch package per rank, sold across every object category.
 *
 * `rank` is ascending-best: the catalog's own ordering contract sorts
 * objects by `tier.rank ASC`, so VIP, the most expensive package, must
 * carry the lowest rank NUMBER (1) to sort first, and the free Standard
 * tier the highest (4) to sort last. An earlier version of this seeder had
 * the two inverted — rank 1 assigned to the free tier — which would have
 * put every free listing ahead of every paying VIP customer in production;
 * caught while building the expiry sweep, before any real data existed.
 */
final class PlacementTierSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $now = now();

        $tiers = [
            [
                'rank' => 4, 'border' => '#D1D5DB', 'badge' => '#6B7280',
                'label' => ['en' => 'Standard', 'ru' => 'Стандарт'],
                'badgeText' => ['en' => 'Standard', 'ru' => 'Стандарт'],
                'package' => [
                    'price' => 0.00, 'validity' => 365, 'bump' => false,
                    'name' => ['en' => 'Free listing', 'ru' => 'Бесплатное размещение'],
                ],
            ],
            [
                'rank' => 3, 'border' => '#93C5FD', 'badge' => '#2563EB',
                'label' => ['en' => 'Business', 'ru' => 'Бизнес'],
                'badgeText' => ['en' => 'Business', 'ru' => 'Бизнес'],
                'package' => [
                    'price' => 29.00, 'validity' => 30, 'bump' => true, 'bumpInterval' => 168, 'freeBumps' => 1, 'paidBump' => 5.00,
                    'name' => ['en' => 'Business package', 'ru' => 'Пакет «Бизнес»'],
                ],
            ],
            [
                'rank' => 2, 'border' => '#FCD34D', 'badge' => '#D97706',
                'label' => ['en' => 'Premium', 'ru' => 'Премиум'],
                'badgeText' => ['en' => 'Premium', 'ru' => 'Премиум'],
                'package' => [
                    'price' => 59.00, 'validity' => 30, 'bump' => true, 'bumpInterval' => 72, 'freeBumps' => 4, 'paidBump' => 4.00,
                    'name' => ['en' => 'Premium package', 'ru' => 'Пакет «Премиум»'],
                ],
            ],
            [
                'rank' => 1, 'border' => '#FCA5A5', 'badge' => '#DC2626',
                'label' => ['en' => 'VIP', 'ru' => 'VIP'],
                'badgeText' => ['en' => 'VIP', 'ru' => 'VIP'],
                'package' => [
                    'price' => 99.00, 'validity' => 30, 'bump' => true, 'bumpInterval' => 24, 'freeBumps' => 30, 'paidBump' => 3.00,
                    'name' => ['en' => 'VIP package', 'ru' => 'Пакет «VIP»'],
                ],
            ],
        ];

        foreach ($tiers as $tier) {
            $tierId = DB::table('placement_tiers')->insertGetId([
                'rank' => $tier['rank'],
                'border_colour' => $tier['border'],
                'badge_colour' => $tier['badge'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach (['en', 'ru'] as $locale) {
                DB::table('placement_tier_translations')->insert([
                    'placement_tier_id' => $tierId,
                    'locale' => $locale,
                    'label' => $tier['label'][$locale],
                    'badge_text' => $tier['badgeText'][$locale],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $package = $tier['package'];
            $packageId = DB::table('placement_packages')->insertGetId([
                'placement_tier_id' => $tierId,
                'price' => $package['price'],
                'currency' => 'EUR',
                'validity_days' => $package['validity'],
                'bump_allowed' => $package['bump'],
                'bump_interval_hours' => $package['bumpInterval'] ?? null,
                'free_bumps_per_period' => $package['freeBumps'] ?? null,
                'paid_bump_price' => $package['paidBump'] ?? null,
                'is_active' => true,
                'display_order' => $tier['rank'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($package['name'] as $locale => $name) {
                DB::table('placement_package_translations')->insert([
                    'placement_package_id' => $packageId,
                    'locale' => $locale,
                    'name' => $name,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
}
