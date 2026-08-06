<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Notifications, Analytics, Platform & Dormant Booking Schema
|--------------------------------------------------------------------------
|
| Two invariants this batch's Verify criterion names directly: stat_events
| is partitioned from creation (not retrofitted once it is the largest
| table in the system), and the three dormant booking tables exist and
| ship empty. A third, module_settings' portal-scope uniqueness, is a raw
| CHECK-adjacent constraint this task introduced and must prove it can fail.
|
*/

test('stat_events is a genuinely partitioned table with at least one child partition', function (): void {
    $partitions = DB::select(
        "select relname from pg_class where relispartition and relname like 'stat_events%'"
    );

    expect($partitions)->not->toBeEmpty();

    // Not just declared — usable. Every column referencing another table
    // is nullable except the ones this row supplies, so the insert alone
    // proves the DEFAULT partition accepts a real row.
    DB::table('stat_events')->insert([
        'kind' => 'object_page_view',
        'subject_type' => 'object',
        'subject_id' => 1,
        'occurred_at' => now(),
    ]);

    expect(DB::table('stat_events')->count())->toBe(1);
});

dataset('dormant_booking_tables', ['reservations', 'room_availabilities', 'booking_settings']);

test('dormant booking tables exist and ship empty', function (string $table): void {
    expect(Schema::hasTable($table))->toBeTrue("Expected {$table} to exist.");
    expect(DB::table($table)->count())->toBe(0);
})->with('dormant_booking_tables');

test('a module setting is unique at portal scope but not limited to one row per module overall', function (): void {
    $module = DB::table('modules')->insertGetId([
        'key' => 'booking',
        'default_state' => 'disabled',
        'scopable_levels' => json_encode(['portal', 'country', 'category', 'owner', 'object']),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $country = DB::table('countries')->insertGetId([
        'code' => 'MD',
        'currency' => 'MDL',
        'phone_code' => '+373',
        'primary_language_id' => DB::table('languages')->insertGetId([
            'code' => 'en',
            'short_label' => 'EN',
            'is_active' => true,
            'is_primary' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]),
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $base = ['module_id' => $module, 'state' => 'enabled', 'set_at' => now(), 'created_at' => now(), 'updated_at' => now()];

    // A portal-scope row and a country-scope row for the same module
    // coexist — the two partial indexes cover different rows, not the
    // same uniqueness question.
    DB::table('module_settings')->insert([...$base, 'scope_level' => 'portal', 'scope_reference_id' => null]);
    DB::table('module_settings')->insert([...$base, 'scope_level' => 'country', 'scope_reference_id' => $country]);

    expect(DB::table('module_settings')->where('module_id', $module)->count())->toBe(2);

    // A second portal-scope row for the same module — Postgres never
    // treats two NULL scope_reference_id values as equal, so without the
    // partial unique index this would silently succeed and resolution
    // would become ambiguous about which row wins.
    expect(fn () => DB::transaction(fn () => DB::table('module_settings')->insert([
        ...$base,
        'scope_level' => 'portal',
        'scope_reference_id' => null,
    ])))->toThrow(QueryException::class);
});
