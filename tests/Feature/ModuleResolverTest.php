<?php

declare(strict_types=1);

use App\Models\Module;
use App\Models\User;
use App\Services\Modules\ModuleAdministrator;
use App\Services\Modules\ModuleContext;
use App\Services\Modules\ModuleResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Module Resolver
|--------------------------------------------------------------------------
|
| Two invariants this task's Verify line names directly: most-specific-wins
| across the full object → owner → category → country → portal → default
| ladder, and a module whose dependency is off resolving to inactive as a
| whole rather than a broken half-capability.
|
*/

function seedModule(string $key, string $defaultState, bool $isActive = true): int
{
    return DB::table('modules')->insertGetId([
        'key' => $key,
        'default_state' => $defaultState,
        'scopable_levels' => json_encode(['portal', 'country', 'category', 'owner', 'object']),
        'is_active' => $isActive,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function setModuleState(int $moduleId, string $level, ?int $referenceId, string $state): void
{
    DB::table('module_settings')->insert([
        'module_id' => $moduleId,
        'scope_level' => $level,
        'scope_reference_id' => $referenceId,
        'state' => $state,
        'set_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // A raw insert bypasses ModuleAdministrator::setState()'s own cache
    // invalidation — the only production write path, and the only one the
    // resolver's registry cache is wired to hear from. Doing that job here
    // too keeps this file free to keep testing the ladder by direct
    // fixture writes instead of routing every case through the admin API.
    Cache::forget("module:settings:{$moduleId}");
}

/**
 * A fresh resolver instance, its per-request memo empty — what a new HTTP
 * request actually gets. Production never mutates `module_settings`
 * mid-request outside `ModuleAdministrator::setState()` (which resets the
 * memo itself), so a test that pokes settings directly between reads, as
 * the ladder-precedence test below does, has to simulate that same
 * request boundary rather than reuse one memoised instance throughout.
 */
function freshModuleResolver(): ModuleResolver
{
    app()->forgetInstance(ModuleResolver::class);

    return app(ModuleResolver::class);
}

test('resolution falls back to the registry default with no settings at any level', function (): void {
    $moduleId = seedModule('demo_module', 'disabled');

    $resolver = app(ModuleResolver::class);

    expect($resolver->isEnabled('demo_module', new ModuleContext))->toBeFalse();
});

test('each level of the ladder wins over every level broader than it', function (): void {
    $moduleId = seedModule('demo_module', 'disabled');
    $context = new ModuleContext(objectId: 1, ownerId: 2, categoryId: 3, countryId: 4);

    // Portal beats the registry default.
    setModuleState($moduleId, 'portal', null, 'enabled');
    expect(freshModuleResolver()->isEnabled('demo_module', $context))->toBeTrue();

    // Country beats portal.
    setModuleState($moduleId, 'country', 4, 'disabled');
    expect(freshModuleResolver()->isEnabled('demo_module', $context))->toBeFalse();

    // Category beats country.
    setModuleState($moduleId, 'category', 3, 'enabled');
    expect(freshModuleResolver()->isEnabled('demo_module', $context))->toBeTrue();

    // Owner beats category.
    setModuleState($moduleId, 'owner', 2, 'disabled');
    expect(freshModuleResolver()->isEnabled('demo_module', $context))->toBeFalse();

    // Object beats owner — the most specific level wins outright.
    setModuleState($moduleId, 'object', 1, 'enabled');
    expect(freshModuleResolver()->isEnabled('demo_module', $context))->toBeTrue();
});

test('a module disabled for another object or country does not affect this one', function (): void {
    $moduleId = seedModule('demo_module', 'disabled');
    setModuleState($moduleId, 'object', 999, 'enabled');
    setModuleState($moduleId, 'country', 999, 'enabled');

    $resolver = app(ModuleResolver::class);

    expect($resolver->isEnabled('demo_module', new ModuleContext(objectId: 1, countryId: 4)))->toBeFalse();
});

test('a retired module resolves to disabled regardless of any enabling setting', function (): void {
    $moduleId = seedModule('demo_module', 'enabled', isActive: false);
    setModuleState($moduleId, 'portal', null, 'enabled');

    expect(app(ModuleResolver::class)->isEnabled('demo_module', new ModuleContext))->toBeFalse();
});

test('an unknown module key resolves to disabled', function (): void {
    expect(app(ModuleResolver::class)->isEnabled('does_not_exist', new ModuleContext))->toBeFalse();
});

test('a module enabled at object scope while its dependency is disabled portal-wide resolves to inactive', function (): void {
    $guestAccountsId = seedModule('guest_accounts', 'disabled');
    $bookingId = seedModule('booking', 'disabled');

    DB::table('module_dependencies')->insert([
        'module_id' => $bookingId,
        'depends_on_module_id' => $guestAccountsId,
    ]);

    // Booking itself is explicitly enabled for this one object...
    setModuleState($bookingId, 'object', 42, 'enabled');
    // ...but its dependency is off everywhere.
    setModuleState($guestAccountsId, 'portal', null, 'disabled');

    $resolver = app(ModuleResolver::class);

    expect($resolver->isEnabled('booking', new ModuleContext(objectId: 42)))
        ->toBeFalse('booking must not resolve enabled while guest_accounts is off — no half-enabled capability.');
});

test('a module resolves enabled when both it and its dependency resolve enabled', function (): void {
    $guestAccountsId = seedModule('guest_accounts', 'disabled');
    $bookingId = seedModule('booking', 'disabled');

    DB::table('module_dependencies')->insert([
        'module_id' => $bookingId,
        'depends_on_module_id' => $guestAccountsId,
    ]);

    setModuleState($bookingId, 'object', 42, 'enabled');
    setModuleState($guestAccountsId, 'portal', null, 'enabled');

    expect(app(ModuleResolver::class)->isEnabled('booking', new ModuleContext(objectId: 42)))->toBeTrue();
});

test('one request resolves a given module at most once', function (): void {
    $moduleId = seedModule('demo_module', 'enabled');
    setModuleState($moduleId, 'portal', null, 'enabled');

    $resolver = app(ModuleResolver::class);

    DB::enableQueryLog();
    $resolver->isEnabled('demo_module', new ModuleContext(objectId: 1));
    $resolver->isEnabled('demo_module', new ModuleContext(objectId: 1));
    $resolver->isEnabled('demo_module', new ModuleContext(objectId: 1));
    $queriesAfterThreeIdenticalCalls = count(DB::getQueryLog());
    DB::disableQueryLog();

    // The first call is the only one allowed to touch the database (or, on
    // a cold cache, Redis) at all — every repeat for the exact same module
    // and context is a pure in-memory memo hit for the life of this
    // resolver instance, which `ModuleResolver` is bound as a singleton
    // precisely so it spans one whole request.
    $resolver->isEnabled('demo_module', new ModuleContext(objectId: 1));
    expect(count(DB::getQueryLog()))->toBe($queriesAfterThreeIdenticalCalls)
        ->and($queriesAfterThreeIdenticalCalls)->toBeGreaterThan(0);
});

test('a different context for the same module resolves independently of an already-memoised one', function (): void {
    $moduleId = seedModule('demo_module', 'disabled');
    setModuleState($moduleId, 'object', 1, 'enabled');

    $resolver = app(ModuleResolver::class);

    expect($resolver->isEnabled('demo_module', new ModuleContext(objectId: 1)))->toBeTrue()
        ->and($resolver->isEnabled('demo_module', new ModuleContext(objectId: 2)))->toBeFalse();
});

test('setting a module invalidates its cached settings for the next resolution in the same request', function (): void {
    $moduleId = seedModule('demo_module', 'disabled');
    $module = Module::query()->findOrFail($moduleId);
    $actor = User::factory()->create();
    $resolver = app(ModuleResolver::class);

    expect($resolver->isEnabled('demo_module', new ModuleContext))->toBeFalse();

    app(ModuleAdministrator::class)->setState($module, 'portal', null, true, $actor);

    // The same resolver instance, already having resolved (and memoised)
    // this exact module/context as disabled above, must still see the
    // write that just happened rather than serve its stale memo.
    expect($resolver->isEnabled('demo_module', new ModuleContext))->toBeTrue();
});
