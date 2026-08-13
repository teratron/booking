<?php

declare(strict_types=1);

use App\Models\Object_;
use App\Models\User;
use App\Services\Objects\ObjectLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * An administrator's own edit publishes directly — no moderation checkpoint
 * applies, since an administrator publishing is already the trusted act.
 * That invariant is only real if the published row also clears the
 * moderation gate every default (non-back-office) query enforces: an
 * object left with a null `moderation_status` is invisible to any query
 * that does not explicitly opt out of that scope, administrator intent
 * notwithstanding.
 */
function objectLifecyclePublicationFixture(): array
{
    $languageId = DB::table('languages')->insertGetId([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $countryId = DB::table('countries')->insertGetId([
        'code' => 'MD', 'currency' => 'MDL', 'phone_code' => '+373',
        'primary_language_id' => $languageId, 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $levelId = DB::table('territory_levels')->insertGetId([
        'country_id' => $countryId, 'depth_rank' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $territoryId = DB::table('territories')->insertGetId([
        'country_id' => $countryId, 'level_id' => $levelId, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $typeId = DB::table('object_types')->insertGetId([
        'key' => 'accommodation', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return compact('countryId', 'territoryId', 'typeId');
}

it('makes an administrator-published object visible under the default moderation-scoped query', function (): void {
    $fixture = objectLifecyclePublicationFixture();
    $actor = User::factory()->create();

    $object = new Object_;
    $object->ulid = (string) Str::ulid();
    $object->object_type_id = $fixture['typeId'];
    $object->territory_id = $fixture['territoryId'];
    $object->country_id = $fixture['countryId'];
    $object->status = 'draft';
    // Deliberately left null — exactly what CreateObject leaves it as; this
    // test proves publish() itself closes the gap, not a fixture shortcut.
    $object->save();

    app(ObjectLifecycleService::class)->publish($object, $actor);

    expect(Object_::query()->find($object->id))->not->toBeNull()
        ->and($object->fresh()->moderation_status)->toBe('approved');
});
