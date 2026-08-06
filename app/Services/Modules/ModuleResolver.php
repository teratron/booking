<?php

declare(strict_types=1);

namespace App\Services\Modules;

use Illuminate\Support\Facades\DB;

/**
 * Resolves a module's effective state, most-specific-wins, down the ladder:
 * object → owner → category → country → portal → registry default. Callers
 * resolve once per request at the boundary and pass the result down —
 * re-resolving inside components produces pages where one section believes
 * a module is on and another does not.
 *
 * Dependency checking happens after a module's own resolution, at the same
 * scope: a module enabled at one level while a dependency is off at a
 * broader level resolves to disabled as a whole, never a half-enabled
 * capability.
 */
final class ModuleResolver
{
    /**
     * Whether $moduleKey is active for $context. A retired module
     * (`modules.is_active = false`) or an unknown key always resolves to
     * disabled, regardless of any setting row.
     */
    public function isEnabled(string $moduleKey, ModuleContext $context): bool
    {
        return $this->resolve($moduleKey, $context, []);
    }

    /**
     * @param  list<string>  $visited  guards against a dependency cycle
     *                                 resolving forever — the current registry has none, but the check
     *                                 costs nothing and a future registry entry should not be able to
     *                                 introduce an infinite recursion by mistake.
     */
    private function resolve(string $moduleKey, ModuleContext $context, array $visited): bool
    {
        if (in_array($moduleKey, $visited, true)) {
            return false;
        }

        /** @var object{id: int, is_active: bool, default_state: string}|null $module */
        $module = DB::table('modules')->where('key', $moduleKey)->first();

        if ($module === null || ! $module->is_active) {
            return false;
        }

        if (! $this->resolveOwnState($module, $context)) {
            return false;
        }

        $visited[] = $moduleKey;

        $dependencyKeys = DB::table('module_dependencies')
            ->join('modules', 'module_dependencies.depends_on_module_id', '=', 'modules.id')
            ->where('module_dependencies.module_id', $module->id)
            ->pluck('modules.key');

        foreach ($dependencyKeys as $dependencyKey) {
            if (! $this->resolve($dependencyKey, $context, $visited)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  object{id: int, is_active: bool, default_state: string}  $module
     */
    private function resolveOwnState(object $module, ModuleContext $context): bool
    {
        $ladder = [
            ['level' => 'object', 'reference' => $context->objectId],
            ['level' => 'owner', 'reference' => $context->ownerId],
            ['level' => 'category', 'reference' => $context->categoryId],
            ['level' => 'country', 'reference' => $context->countryId],
            ['level' => 'portal', 'reference' => null],
        ];

        foreach ($ladder as $rung) {
            if ($rung['level'] !== 'portal' && $rung['reference'] === null) {
                continue;
            }

            $query = DB::table('module_settings')
                ->where('module_id', $module->id)
                ->where('scope_level', $rung['level']);

            $query = $rung['level'] === 'portal'
                ? $query->whereNull('scope_reference_id')
                : $query->where('scope_reference_id', $rung['reference']);

            $setting = $query->first();

            if ($setting !== null) {
                return $setting->state === 'enabled';
            }
        }

        return $module->default_state === 'enabled';
    }
}
