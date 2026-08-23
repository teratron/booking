<?php

declare(strict_types=1);

use App\Models\Module;
use App\Models\User;
use App\Services\Modules\ModuleAdministrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Public API — Module Gate
|--------------------------------------------------------------------------
|
| The API is a gateable module, disabled at portal scope by default. A
| request against it 404s — the inertness rule every other gated module in
| this codebase already follows — and a disabled module must never let a
| visitor tell an existing route apart from one that was never registered.
|
*/

function apiModuleFixture(): int
{
    return DB::table('modules')->insertGetId([
        'key' => 'api', 'default_state' => 'disabled', 'scopable_levels' => json_encode(['portal']),
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

it('404s an existing endpoint while the api module is disabled', function (): void {
    apiModuleFixture();

    $this->getJson('/api/v1/status')->assertNotFound();
});

it('404s a route that was never registered, indistinguishably from a disabled existing one', function (): void {
    apiModuleFixture();

    $existing = $this->getJson('/api/v1/status');
    $unregistered = $this->getJson('/api/v1/this-route-does-not-exist');

    $existing->assertNotFound();
    $unregistered->assertNotFound();
    expect($existing->getStatusCode())->toBe($unregistered->getStatusCode());
});

it('serves an existing endpoint once the api module is enabled at portal scope', function (): void {
    $moduleId = apiModuleFixture();
    $module = Module::query()->findOrFail($moduleId);
    $actor = User::factory()->create();

    app(ModuleAdministrator::class)->setState($module, 'portal', null, true, $actor);

    $this->getJson('/api/v1/status')
        ->assertOk()
        ->assertJson(['status' => 'ok', 'version' => 'v1']);
});

it('still 404s an unregistered route once the api module is enabled', function (): void {
    $moduleId = apiModuleFixture();
    $module = Module::query()->findOrFail($moduleId);
    $actor = User::factory()->create();

    app(ModuleAdministrator::class)->setState($module, 'portal', null, true, $actor);

    $this->getJson('/api/v1/this-route-does-not-exist')->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| Gate Ordering on a Token-Protected Route
|--------------------------------------------------------------------------
|
| /status carries no auth:sanctum middleware, so every test above passes
| even if the module gate runs after authentication — nothing upstream of
| it can produce a different status code. /objects does carry auth:sanctum,
| which is the one shape that exposes the priority-list anchor actually
| taking effect: an anonymous, tokenless request must still see the module
| as absent (404), never as "present but unauthenticated" (401) — the
| ordering bootstrap/app.php's own prependToPriorityList() call exists to
| guarantee.
|
*/

it('404s a token-protected endpoint for an anonymous caller while the api module is disabled', function (): void {
    apiModuleFixture();

    $this->getJson('/api/v1/objects')->assertNotFound();
});
