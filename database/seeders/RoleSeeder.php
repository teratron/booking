<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds the launch role set observed across the back-office requirements —
 * illustrative, not exhaustive: administrators may create further roles
 * from the back office, which is exactly why roles are data rather than an
 * enumeration in code. `chief_administrator` is the one role this project's
 * own governance rule protects from ever reaching zero holders — see
 * RoleGrantService.
 */
final class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // The outer DatabaseSeeder wraps every call() in Model::withoutEvents(),
        // which suppresses the model `saved` event spatie/laravel-permission
        // relies on to invalidate its own permission cache — without this,
        // givePermissionTo() below reads a stale, empty cache and fails with
        // "no permission named ..." even though PermissionSeeder already
        // committed the rows.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $roles = [
            [
                'key' => 'chief_administrator', 'system' => true,
                'name' => ['en' => 'Chief Administrator', 'ru' => 'Главный администратор'],
                'permissions' => ['*'],
            ],
            [
                'key' => 'country_administrator', 'system' => true,
                'name' => ['en' => 'Country Administrator', 'ru' => 'Администратор страны'],
                'permissions' => ['object.*', 'content.*', 'moderation.*', 'geography.*', 'user_management', 'admin_panel_access', 'audit.view'],
            ],
            [
                'key' => 'region_administrator', 'system' => true,
                'name' => ['en' => 'Region Administrator', 'ru' => 'Региональный администратор'],
                'permissions' => ['object.view', 'object.edit', 'object.publish', 'content.view', 'content.edit', 'content.publish', 'geography.view', 'geography.edit', 'admin_panel_access'],
            ],
            [
                'key' => 'moderator', 'system' => true,
                'name' => ['en' => 'Moderator', 'ru' => 'Модератор'],
                'permissions' => ['object.view', 'object.edit', 'object.publish', 'moderation.*', 'admin_panel_access', 'audit.view'],
            ],
            [
                'key' => 'content_manager', 'system' => true,
                'name' => ['en' => 'Content Manager', 'ru' => 'Контент-менеджер'],
                'permissions' => ['content.*', 'admin_panel_access'],
            ],
            [
                'key' => 'seo_specialist', 'system' => true,
                'name' => ['en' => 'SEO Specialist', 'ru' => 'SEO-специалист'],
                'permissions' => ['object.view', 'object.edit', 'content.view', 'content.edit', 'admin_panel_access'],
            ],
            [
                'key' => 'advertising_manager', 'system' => true,
                'name' => ['en' => 'Advertising Manager', 'ru' => 'Менеджер по рекламе'],
                'permissions' => ['content.*', 'object.view', 'admin_panel_access'],
            ],
            [
                'key' => 'finance_manager', 'system' => true,
                'name' => ['en' => 'Finance Manager', 'ru' => 'Финансовый менеджер'],
                'permissions' => ['finance.*', 'financial_access', 'admin_panel_access'],
            ],
            [
                'key' => 'technical_support', 'system' => true,
                'name' => ['en' => 'Technical Support', 'ru' => 'Техническая поддержка'],
                'permissions' => ['settings.view', 'settings_management', 'admin_panel_access', 'audit.view', 'impersonate'],
            ],
            [
                'key' => 'object_owner', 'system' => true,
                'name' => ['en' => 'Object Owner', 'ru' => 'Владелец объекта'],
                // `content.*` covers the owner's own news/promotion
                // authoring screens — scoped down to just this object's own
                // rows by `CabinetAccessResolver`, the same ownership check
                // `object.edit` already relies on, not by a broader grant.
                'permissions' => ['object.view', 'object.edit', 'content.view', 'content.create', 'content.edit', 'cabinet_access'],
            ],
            [
                'key' => 'object_staff_member', 'system' => true,
                'name' => ['en' => 'Object Staff Member', 'ru' => 'Сотрудник объекта'],
                'permissions' => ['object.view', 'cabinet_access'],
            ],
        ];

        $allPermissions = [...PermissionSeeder::resourceVerbKeys(), ...PermissionSeeder::standaloneKeys()];

        foreach ($roles as $role) {
            $model = Role::create([
                'name' => $role['key'],
                'guard_name' => 'web',
            ]);

            DB::table('roles')->where('id', $model->id)->update(['is_system' => $role['system']]);

            foreach ($role['name'] as $locale => $displayName) {
                DB::table('role_translations')->insert([
                    'role_id' => $model->id,
                    'locale' => $locale,
                    'display_name' => $displayName,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $model->givePermissionTo($this->resolvePermissions($role['permissions'], $allPermissions));
        }
    }

    /**
     * Expands the role's declared permission patterns — a literal key, a
     * `resource.*` wildcard, or `*` for every permission in the system —
     * into the concrete permission key list spatie/laravel-permission needs.
     *
     * @param  list<string>  $patterns
     * @param  list<string>  $allPermissions
     * @return list<string>
     */
    private function resolvePermissions(array $patterns, array $allPermissions): array
    {
        if ($patterns === ['*']) {
            return $allPermissions;
        }

        $resolved = [];
        foreach ($patterns as $pattern) {
            if (str_ends_with($pattern, '.*')) {
                $prefix = substr($pattern, 0, -1);
                $resolved = [...$resolved, ...array_filter($allPermissions, static fn (string $key): bool => str_starts_with($key, $prefix))];

                continue;
            }

            $resolved[] = $pattern;
        }

        return array_values(array_unique($resolved));
    }
}
