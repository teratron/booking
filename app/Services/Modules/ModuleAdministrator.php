<?php

declare(strict_types=1);

namespace App\Services\Modules;

use App\Exceptions\ModuleDependencyException;
use App\Models\Module;
use App\Models\User;
use App\Services\Audit\AuditJournal;
use Illuminate\Support\Facades\DB;

/**
 * Reads and writes module state on behalf of the administration screen.
 *
 * Resolution itself is not re-implemented here: `ModuleResolver` owns the
 * ladder, and a second implementation inside the screen is exactly how two
 * answers to the same question appear. This adds what the screen needs on top
 * — the effective state at each rung, the real count of records a toggle would
 * affect, and the dependency check that has to run before a write.
 */
final class ModuleAdministrator
{
    public function __construct(
        private readonly ModuleResolver $resolver,
        private readonly AuditJournal $journal,
    ) {}

    /**
     * The module's effective state at a given rung, resolved through the same
     * ladder the runtime gate uses.
     */
    public function effectiveState(Module $module, string $scopeLevel, ?int $reference): bool
    {
        return $this->resolver->isEnabled($module->key, $this->contextFor($scopeLevel, $reference));
    }

    /**
     * How many objects a toggle at this scope would change behaviour for.
     *
     * Counted, never estimated. "Enabling booking for Ukraine affects 412
     * objects" is the required form of the confirmation, and a number that is
     * wrong is worse than no number — an administrator who learns the count
     * cannot be trusted stops reading it.
     */
    public function blastRadius(string $scopeLevel, ?int $reference): int
    {
        $query = DB::table('objects')->whereNull('deleted_at');

        return match ($scopeLevel) {
            'portal' => $query->count(),
            'country' => $query->where('country_id', $reference)->count(),
            'category' => $query->where('object_type_id', $reference)->count(),
            'owner' => $query->where('owner_id', $reference)->count(),
            'object' => $query->where('id', $reference)->count(),
            default => 0,
        };
    }

    /**
     * Sets $module's state at one rung and journals the change.
     *
     * @throws ModuleDependencyException when enabling would leave a dependency off,
     *                                   or would turn on a module that conflicts with one already on
     */
    public function setState(Module $module, string $scopeLevel, ?int $reference, bool $enabled, User $actor): void
    {
        if ($enabled) {
            $this->assertDependenciesSatisfied($module, $scopeLevel, $reference);
            $this->assertNoConflictEnabled($module, $scopeLevel, $reference);
        }

        $previous = $this->effectiveState($module, $scopeLevel, $reference);

        DB::table('module_settings')->updateOrInsert(
            [
                'module_id' => $module->id,
                'scope_level' => $scopeLevel,
                'scope_reference_id' => $reference,
            ],
            [
                'state' => $enabled ? 'enabled' : 'disabled',
                'set_by' => $actor->id,
                'set_at' => now(),
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        // The resolver caches this same registry in Redis, keyed by module
        // id — this is the write that cache exists to notice.
        $this->resolver->invalidateSettingsCache((int) $module->id);

        $this->journal->record(
            event: 'module_toggled',
            target: $module,
            oldValues: ['state' => $previous ? 'enabled' : 'disabled'],
            newValues: [
                'state' => $enabled ? 'enabled' : 'disabled',
                'scope_level' => $scopeLevel,
                'scope_reference_id' => $reference,
                'affected_objects' => $this->blastRadius($scopeLevel, $reference),
            ],
            actor: $actor,
            tags: ['modules'],
        );
    }

    private function assertDependenciesSatisfied(Module $module, string $scopeLevel, ?int $reference): void
    {
        foreach ($module->dependencies as $dependency) {
            if (! $this->effectiveState($dependency, $scopeLevel, $reference)) {
                throw ModuleDependencyException::unmet($module->key, $dependency->key);
            }
        }
    }

    private function assertNoConflictEnabled(Module $module, string $scopeLevel, ?int $reference): void
    {
        foreach ($module->conflicts as $conflict) {
            if ($this->effectiveState($conflict, $scopeLevel, $reference)) {
                throw ModuleDependencyException::conflicting($module->key, $conflict->key);
            }
        }
    }

    private function contextFor(string $scopeLevel, ?int $reference): ModuleContext
    {
        return match ($scopeLevel) {
            'country' => new ModuleContext(countryId: $reference),
            'category' => new ModuleContext(categoryId: $reference),
            'owner' => new ModuleContext(ownerId: $reference),
            'object' => new ModuleContext(objectId: $reference),
            default => new ModuleContext,
        };
    }
}
