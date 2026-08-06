<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

/**
 * Seeds the fixed permission set — unlike roles, permissions are tied to an
 * actual code-enforced gate rather than administrator-invented data, so this
 * set grows only when a new gate is written, not through the back office.
 * Each key is `{resource}.{verb}`; three verbs (financial access, user
 * management, settings management) stand alone rather than crossing every
 * resource, since they name a capability, not a per-resource CRUD action.
 */
final class PermissionSeeder extends Seeder
{
    /** @return list<string> */
    public static function resourceVerbKeys(): array
    {
        $resources = ['object', 'content', 'moderation', 'finance', 'user', 'settings'];
        $verbs = ['view', 'create', 'edit', 'publish', 'delete', 'export'];

        $keys = [];
        foreach ($resources as $resource) {
            foreach ($verbs as $verb) {
                $keys[] = "{$resource}.{$verb}";
            }
        }

        return $keys;
    }

    /** @return list<string> */
    public static function standaloneKeys(): array
    {
        return ['financial_access', 'user_management', 'settings_management'];
    }

    public function run(): void
    {
        foreach ([...self::resourceVerbKeys(), ...self::standaloneKeys()] as $key) {
            Permission::findOrCreate($key, 'web');
        }
    }
}
