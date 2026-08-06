<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Module Inertness — Disabled Means Absent, Both Directions
|--------------------------------------------------------------------------
|
| Proven against the real `booking`/`guest_accounts` registry rows and
| their real dependency edge, not a demo module — a route behind `booking`
| must return a plain 404 (indistinguishable from a route that does not
| exist) while disabled, resolve normally once genuinely enabled, and
| leave a non-participating object gated even when a sibling object has an
| explicit object-scope override. No real gated route, scheduled job, or
| rendered object page exists yet (Phase 5 builds the booking surface
| itself) — see this task's own Execution findings for what that leaves
| unverifiable for now, checked honestly rather than faked.
|
*/

beforeEach(function (): void {
    Route::middleware('web')->get('/__test/booking-gated/{object}', fn (int $object) => "reachable:{$object}")
        ->middleware('module:booking');

    $this->guestAccountsId = DB::table('modules')->insertGetId([
        'key' => 'guest_accounts', 'default_state' => 'disabled',
        'scopable_levels' => json_encode(['portal']), 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->bookingId = DB::table('modules')->insertGetId([
        'key' => 'booking', 'default_state' => 'disabled',
        'scopable_levels' => json_encode(['portal', 'country', 'category', 'owner', 'object']),
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('module_dependencies')->insert([
        'module_id' => $this->bookingId,
        'depends_on_module_id' => $this->guestAccountsId,
    ]);
});

test('a route behind a disabled booking module returns a plain 404', function (): void {
    $this->get('/__test/booking-gated/42')->assertNotFound();
});

test('enabling booking for one object while guest_accounts stays off portal-wide still 404s — no half-enabled route', function (): void {
    DB::table('module_settings')->insert([
        'module_id' => $this->bookingId, 'scope_level' => 'object', 'scope_reference_id' => 42,
        'state' => 'enabled', 'set_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->get('/__test/booking-gated/42')->assertNotFound();
});

test('once both booking and its dependency are genuinely enabled, the gated route resolves for that object only', function (): void {
    DB::table('module_settings')->insert([
        'module_id' => $this->guestAccountsId, 'scope_level' => 'portal', 'scope_reference_id' => null,
        'state' => 'enabled', 'set_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('module_settings')->insert([
        'module_id' => $this->bookingId, 'scope_level' => 'object', 'scope_reference_id' => 42,
        'state' => 'enabled', 'set_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->get('/__test/booking-gated/42')->assertOk()->assertSee('reachable:42');

    // A non-participating object — no object-scope override of its own —
    // stays gated even though booking is now active elsewhere.
    $this->get('/__test/booking-gated/99')->assertNotFound();
});
