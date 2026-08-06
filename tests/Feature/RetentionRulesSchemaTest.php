<?php

declare(strict_types=1);

use App\Models\Concerns\FiltersModeration;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Retention Rules — Append-Only Privileges & Moderation/Soft-Delete Scope
|--------------------------------------------------------------------------
|
| Two invariants, proven the way this project proves every constraint: by
| showing the specific expected failure, not by trusting the migration read
| correctly. The append-only guarantee is enforced at the Postgres privilege
| level (table ownership moved off the application role, which is also
| stripped of its superuser bit — see the migration for why both were
| necessary), so the expected failure here is a real permission-denied
| exception, not a silently-affected zero rows. The moderation/soft-delete
| guarantee is enforced by a global scope, proven against a minimal fixture
| model bound to the real `objects` table rather than the eventual
| production model, which is T-1D03's own boundary to define.
|
*/

/**
 * Minimal Eloquent binding to `objects`, scoped to exactly what this task
 * owns — soft-delete and moderation filtering. Relations, casts, and the
 * remaining package traits are T-1D03's responsibility, not reproduced here.
 */
final class RetentionFixtureObject extends Model
{
    use FiltersModeration;
    use SoftDeletes;

    protected $table = 'objects';

    protected $guarded = [];
}

function seedRetentionGeographyChain(): int
{
    $languageId = DB::table('languages')->insertGetId([
        'code' => 'en',
        'short_label' => 'EN',
        'is_active' => true,
        'is_primary' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $countryId = DB::table('countries')->insertGetId([
        'code' => 'MD',
        'currency' => 'MDL',
        'phone_code' => '+373',
        'primary_language_id' => $languageId,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $levelId = DB::table('territory_levels')->insertGetId([
        'country_id' => $countryId,
        'depth_rank' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return DB::table('territories')->insertGetId([
        'country_id' => $countryId,
        'level_id' => $levelId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

test('the application role is denied UPDATE and DELETE on the audit journal', function (): void {
    $auditId = DB::table('audits')->insertGetId([
        'event' => 'created',
        'auditable_type' => 'App\\Models\\Object',
        'auditable_id' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => DB::transaction(
        fn () => DB::table('audits')->where('id', $auditId)->update(['event' => 'updated'])
    ))->toThrow(QueryException::class);

    expect(fn () => DB::transaction(
        fn () => DB::table('audits')->where('id', $auditId)->delete()
    ))->toThrow(QueryException::class);

    // Not a silent no-op: the row is untouched, not merely zero-rows-matched.
    expect(DB::table('audits')->where('id', $auditId)->value('event'))->toBe('created');
});

test('the application role is denied UPDATE and DELETE on financial records', function (): void {
    $slotId = DB::table('banner_slots')->insertGetId([
        'key' => 'home-hero',
        'surfaces' => json_encode(['home']),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $bannerId = DB::table('banners')->insertGetId([
        'banner_slot_id' => $slotId,
        'name' => 'Retention test banner',
        'advertiser' => 'Test Advertiser',
        'destination_link' => 'https://example.test',
        'starts_at' => now()->toDateString(),
        'ends_at' => now()->addMonth()->toDateString(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $recordId = DB::table('financial_records')->insertGetId([
        'banner_id' => $bannerId,
        'service' => 'banner_placement',
        'amount' => 100.00,
        'currency' => 'MDL',
        'status' => 'paid',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => DB::transaction(
        fn () => DB::table('financial_records')->where('id', $recordId)->update(['amount' => 200.00])
    ))->toThrow(QueryException::class);

    expect(fn () => DB::transaction(
        fn () => DB::table('financial_records')->where('id', $recordId)->delete()
    ))->toThrow(QueryException::class);

    expect((float) DB::table('financial_records')->where('id', $recordId)->value('amount'))->toBe(100.00);
});

test('a soft-deleted object and an unmoderated object are both absent from an unqualified query', function (): void {
    $territoryId = seedRetentionGeographyChain();

    $objectTypeId = DB::table('object_types')->insertGetId([
        'key' => 'hotel',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $ownerId = DB::table('users')->insertGetId([
        'name' => 'Retention Test Owner',
        'email' => 'retention-owner@example.test',
        'password' => bcrypt('password'),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $countryId = DB::table('territories')->where('id', $territoryId)->value('country_id');

    $base = [
        'owner_id' => $ownerId,
        'object_type_id' => $objectTypeId,
        'territory_id' => $territoryId,
        'country_id' => $countryId,
        'status' => 'published',
        'created_at' => now(),
        'updated_at' => now(),
    ];

    $visibleId = DB::table('objects')->insertGetId([...$base, 'ulid' => Str::ulid(), 'moderation_status' => 'approved']);
    $softDeletedId = DB::table('objects')->insertGetId([
        ...$base, 'ulid' => Str::ulid(), 'moderation_status' => 'approved', 'deleted_at' => now(),
    ]);
    $unmoderatedId = DB::table('objects')->insertGetId([...$base, 'ulid' => Str::ulid(), 'moderation_status' => 'pending']);

    $visibleIds = RetentionFixtureObject::query()->pluck('id');

    expect($visibleIds)->toContain($visibleId);
    expect($visibleIds)->not->toContain($softDeletedId);
    expect($visibleIds)->not->toContain($unmoderatedId);

    // The escape hatch exists for moderation-review contexts and must
    // actually work — an unproven bypass is a design risk the same way an
    // unproven constraint is.
    $reviewableIds = RetentionFixtureObject::withUnmoderated()->pluck('id');
    expect($reviewableIds)->toContain($unmoderatedId);
});
