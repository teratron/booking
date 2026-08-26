<?php

declare(strict_types=1);

namespace App\Services\Modules;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
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
 *
 * Bound as a singleton (see `AppServiceProvider`) so the per-request memo
 * below actually spans the whole request: a page resolving the same module
 * for the same context from several unrelated call sites — the object page
 * alone does this eight times over — pays the resolution cost once. Behind
 * the memo, the registry rows a resolution reads (`modules`,
 * `module_settings`, `module_dependencies`) are cached in Redis, since the
 * whole registry is small and changes rarely; `ModuleAdministrator::setState()`
 * is the only write path and invalidates both layers together.
 */
final class ModuleResolver
{
    private const int CACHE_TTL_SECONDS = 300;

    /** @var array<string, bool> */
    private array $memo = [];

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
     * Drops the cached settings rows for $moduleId and clears the whole
     * per-request memo — called after any write to `module_settings`. The
     * memo is cleared wholesale rather than by key: a write inside a
     * request most often happens through an admin action that itself asked
     * `isEnabled()` first (a dependency or conflict check), so the memo may
     * already hold answers computed before this write — answers a later
     * step of the same request must not keep trusting.
     */
    public function invalidateSettingsCache(int $moduleId): void
    {
        Cache::forget("module:settings:{$moduleId}");
        $this->memo = [];
    }

    /**
     * @param  list<string>  $visited  guards against a dependency cycle
     *                                 resolving forever — the current registry has none, but the check
     *                                 costs nothing and a future registry entry should not be able to
     *                                 introduce an infinite recursion by mistake.
     */
    private function resolve(string $moduleKey, ModuleContext $context, array $visited): bool
    {
        $memoKey = $this->memoKey($moduleKey, $context);

        if (array_key_exists($memoKey, $this->memo)) {
            return $this->memo[$memoKey];
        }

        // A cycle-guard short-circuit is not a final answer for this key —
        // it says nothing about what the module resolves to outside this
        // one recursive chain, so it is returned but never memoised.
        if (in_array($moduleKey, $visited, true)) {
            return false;
        }

        $result = $this->resolveUncached($moduleKey, $context, $visited);
        $this->memo[$memoKey] = $result;

        return $result;
    }

    /**
     * @param  list<string>  $visited
     */
    private function resolveUncached(string $moduleKey, ModuleContext $context, array $visited): bool
    {
        $module = $this->moduleRow($moduleKey);

        if ($module === null || ! $module->is_active) {
            return false;
        }

        if (! $this->resolveOwnState($module, $context)) {
            return false;
        }

        $visited[] = $moduleKey;

        foreach ($this->dependencyKeysFor((int) $module->id) as $dependencyKey) {
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
        $settings = $this->settingsFor((int) $module->id);

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

            $setting = $settings->first(
                static fn (object $row): bool => $row->scope_level === $rung['level']
                    && self::normalizeReference($row->scope_reference_id) === $rung['reference'],
            );

            if ($setting !== null) {
                return $setting->state === 'enabled';
            }
        }

        return $module->default_state === 'enabled';
    }

    /**
     * The registry is small and changes rarely (an administrator flipping a
     * module or editing its dependencies), so each piece is cached whole
     * rather than re-queried on every resolution.
     *
     * @return object{id: int, is_active: bool, default_state: string}|null
     */
    private function moduleRow(string $moduleKey): ?object
    {
        /** @var object{id: int, is_active: bool, default_state: string}|null */
        return Cache::remember(
            "module:row:{$moduleKey}",
            self::CACHE_TTL_SECONDS,
            static fn () => DB::table('modules')->where('key', $moduleKey)->first(),
        );
    }

    /**
     * @return Collection<int, object{scope_level: string, scope_reference_id: int|string|null, state: string}>
     */
    private function settingsFor(int $moduleId): Collection
    {
        /** @var Collection<int, object{scope_level: string, scope_reference_id: int|string|null, state: string}> */
        return Cache::remember(
            "module:settings:{$moduleId}",
            self::CACHE_TTL_SECONDS,
            static fn () => DB::table('module_settings')->where('module_id', $moduleId)->get(),
        );
    }

    /**
     * @return Collection<int, string>
     */
    private function dependencyKeysFor(int $moduleId): Collection
    {
        return Cache::remember(
            "module:dependencies:{$moduleId}",
            self::CACHE_TTL_SECONDS,
            static fn () => DB::table('module_dependencies')
                ->join('modules', 'module_dependencies.depends_on_module_id', '=', 'modules.id')
                ->where('module_dependencies.module_id', $moduleId)
                ->pluck('modules.key'),
        );
    }

    /**
     * Postgres can hand a foreign-key column back as either an int or a
     * numeric string depending on the driver's fetch mode; the ladder
     * compares it against a plain `?int` from `ModuleContext`, so both
     * sides are normalised to the same type before the comparison — a
     * loose `==` would risk `0 == null` being true for a portal row read
     * back with the wrong shape.
     */
    private static function normalizeReference(int|string|null $reference): ?int
    {
        return $reference === null ? null : (int) $reference;
    }

    private function memoKey(string $moduleKey, ModuleContext $context): string
    {
        return sprintf(
            '%s|%s|%s|%s|%s',
            $moduleKey,
            $context->objectId ?? '-',
            $context->ownerId ?? '-',
            $context->categoryId ?? '-',
            $context->countryId ?? '-',
        );
    }
}
