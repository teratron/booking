<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            LanguageSeeder::class,
            CountrySeeder::class,
            TerritoryLevelSeeder::class,
            ObjectTypeSeeder::class,
            SeoMetadataTemplateSeeder::class,
            AmenitySeeder::class,
            ContactChannelTypeSeeder::class,
            PlacementTierSeeder::class,
            ModuleSeeder::class,
            NotificationChannelSeeder::class,
            NotificationTypeSeeder::class,
            NotificationTemplateSeeder::class,
            PermissionSeeder::class,
            RoleSeeder::class,
        ]);

        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $user->assignRole('chief_administrator');
    }
}
