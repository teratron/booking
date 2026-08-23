<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Services\Authorization\RoleGrantService;
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

        // grantRole(), not assignRole(): the bare Spatie assignment this
        // replaced left the account with a permission but no matching
        // role_scopes row, which ScopeAuthorizer reads as "reaches no axis"
        // — every scoped resource in the back office failed closed for the
        // portal's only seeded administrator. Self-attributed granted_by:
        // no other account exists yet to grant it.
        app(RoleGrantService::class)->grantRole($user, 'chief_administrator', $user);
    }
}
