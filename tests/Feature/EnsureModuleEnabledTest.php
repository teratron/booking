<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Ensure Module Enabled (Server-Side Gate)
|--------------------------------------------------------------------------
|
| "Disabled means inert, not hidden" — a route behind a disabled module
| returns 404, indistinguishable from a route that does not exist, not a
| rendered page missing the capability. No real gated route exists yet,
| so this proves the gate itself against a scratch route registered for
| the test alone.
|
*/

beforeEach(function (): void {
    Route::middleware('web')->get('/__test/gated', fn () => 'reachable')->middleware('module:demo_module');
});

test('a request behind an enabled module reaches the route', function (): void {
    DB::table('modules')->insert([
        'key' => 'demo_module', 'default_state' => 'enabled',
        'scopable_levels' => json_encode(['portal']), 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->get('/__test/gated')->assertOk()->assertSee('reachable');
});

test('a request behind a disabled module gets a 404, not a rendered page missing the capability', function (): void {
    DB::table('modules')->insert([
        'key' => 'demo_module', 'default_state' => 'disabled',
        'scopable_levels' => json_encode(['portal']), 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->get('/__test/gated')->assertNotFound();
});
