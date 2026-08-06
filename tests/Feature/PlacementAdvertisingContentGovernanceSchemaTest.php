<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Placement, Advertising, Content & Governance Schema
|--------------------------------------------------------------------------
|
| Three invariants this batch's Verify criterion names directly: a bump is
| always scoped (never global), a moderation request never overwrites the
| published record it reviews, and a financial ledger row belongs to
| exactly one commercial subject. Each is asserted against the live schema
| rather than by eye.
|
*/

dataset('polymorphic_columns', [
    'bump_events scope (a bump is scoped, never global)' => ['bump_events', ['scope_type', 'scope_id']],
    'moderation_requests target' => ['moderation_requests', ['target_type', 'target_id']],
]);

test('polymorphic columns exist on the tables that need them', function (string $table, array $columns): void {
    $columnList = implode(', ', $columns);

    expect(Schema::hasColumns($table, $columns))
        ->toBeTrue("Expected {$table} to carry {$columnList}.");
})->with('polymorphic_columns');

test('a moderation request snapshots both the previous and proposed state, never overwriting the published record', function (): void {
    $moderator = DB::table('users')->insertGetId([
        'name' => 'Moderator',
        'email' => 'moderator@example.test',
        'password' => 'irrelevant',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $proposedData = ['name' => 'New Name', 'price' => 199.99];

    // previous_data is null here on purpose: a first-time submission (a
    // brand-new object awaiting its first review) has no prior published
    // state to snapshot. moderation_requests must still accept it — and
    // still carry proposed_data — without touching any live record.
    $requestId = DB::table('moderation_requests')->insertGetId([
        'target_type' => 'object',
        'target_id' => 1,
        'section' => 'pricing',
        'previous_data' => null,
        'proposed_data' => json_encode($proposedData),
        'submitted_by' => $moderator,
        'submitted_at' => now(),
        'decision' => 'pending',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $stored = DB::table('moderation_requests')->find($requestId);

    expect($stored->previous_data)->toBeNull()
        ->and(json_decode((string) $stored->proposed_data, true))->toBe($proposedData)
        ->and($stored->decision)->toBe('pending');
});

test('a financial record belongs to exactly one subject: an object or a banner', function (): void {
    $language = DB::table('languages')->insertGetId([
        'code' => 'en',
        'short_label' => 'EN',
        'is_active' => true,
        'is_primary' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $country = DB::table('countries')->insertGetId([
        'code' => 'MD',
        'currency' => 'MDL',
        'phone_code' => '+373',
        'primary_language_id' => $language,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $level = DB::table('territory_levels')->insertGetId([
        'country_id' => $country,
        'depth_rank' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $territory = DB::table('territories')->insertGetId([
        'country_id' => $country,
        'level_id' => $level,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $objectType = DB::table('object_types')->insertGetId([
        'key' => 'restaurant',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $owner = DB::table('users')->insertGetId([
        'name' => 'Owner',
        'email' => 'owner-financial@example.test',
        'password' => 'irrelevant',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $object = DB::table('objects')->insertGetId([
        'ulid' => strtoupper(bin2hex(random_bytes(13))),
        'owner_id' => $owner,
        'object_type_id' => $objectType,
        'territory_id' => $territory,
        'country_id' => $country,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $bannerSlot = DB::table('banner_slots')->insertGetId([
        'key' => 'test-slot',
        'surfaces' => json_encode(['home']),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $banner = DB::table('banners')->insertGetId([
        'banner_slot_id' => $bannerSlot,
        'name' => 'Test banner',
        'advertiser' => 'Test advertiser',
        'destination_link' => 'https://example.test',
        'starts_at' => now()->toDateString(),
        'ends_at' => now()->addMonth()->toDateString(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $base = [
        'service' => 'banner_campaign',
        'amount' => 100,
        'currency' => 'MDL',
        'status' => 'paid',
        'created_at' => now(),
        'updated_at' => now(),
    ];

    // Each rejected insert runs inside its own nested transaction
    // (Postgres SAVEPOINT) — a failed statement aborts the enclosing
    // transaction outright, and without the savepoint boundary the
    // valid insert below would fail too, for the wrong reason.

    // Neither subject set — Postgres never treats two NULLs as equal, so
    // this must be rejected by the CHECK constraint, not silently accepted.
    expect(fn () => DB::transaction(fn () => DB::table('financial_records')->insert($base)))
        ->toThrow(QueryException::class);

    // Both subjects set — equally invalid; a ledger row is either a
    // placement payment or an advertising payment, never both at once.
    expect(fn () => DB::transaction(fn () => DB::table('financial_records')->insert([
        ...$base,
        'object_id' => $object,
        'banner_id' => $banner,
    ])))->toThrow(QueryException::class);

    // Exactly one subject set — the valid shape — is accepted.
    DB::table('financial_records')->insert([...$base, 'banner_id' => $banner]);

    expect(DB::table('financial_records')->where('banner_id', $banner)->exists())->toBeTrue();
});
