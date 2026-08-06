<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Object Attribute Bag
|--------------------------------------------------------------------------
|
| Filterable, per-type fields are typed columns; the type-specific remainder
| lives in objects.attributes as JSONB, validated against the object type's
| own attribute_schema. This proves the bag round-trips typed values —
| strings, numbers, and booleans distinguishable on the way back out — not
| merely that a JSON blob was accepted.
|
*/

test('a type-specific attribute bag round-trips typed through JSONB', function (): void {
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
        // The type's own declared schema — objects.attributes is validated
        // against this at the application layer (a later task); the
        // migration only proves the column shape holds arbitrary,
        // type-declared JSON.
        'attribute_schema' => json_encode([
            'cuisine' => 'string',
            'average_cheque' => 'number',
            'accepts_reservations' => 'boolean',
        ]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $owner = DB::table('users')->insertGetId([
        'name' => 'Owner',
        'email' => 'owner@example.test',
        'password' => 'irrelevant',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $attributes = [
        'cuisine' => 'Moldovan',
        'average_cheque' => 250.5,
        'accepts_reservations' => true,
    ];

    $objectId = DB::table('objects')->insertGetId([
        'ulid' => strtoupper(bin2hex(random_bytes(13))),
        'owner_id' => $owner,
        'object_type_id' => $objectType,
        'territory_id' => $territory,
        'country_id' => $country,
        'attributes' => json_encode($attributes),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $stored = DB::table('objects')->where('id', $objectId)->first();
    $decoded = json_decode((string) $stored->attributes, true);

    expect($decoded)->toBe($attributes)
        ->and($decoded['cuisine'])->toBeString()
        ->and($decoded['average_cheque'])->toBeFloat()
        ->and($decoded['accepts_reservations'])->toBeBool();
});
