<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Translation Table Schema
|--------------------------------------------------------------------------
|
| Every content-bearing table in this batch has a sibling *_translations
| table, unique on (entity foreign key, locale) — the astrotomic/laravel-
| translatable shape. Enumerated explicitly rather than checked by eye: a
| missing pair here means a table that cannot carry translated text at all.
|
*/

dataset('content_bearing_tables', [
    'countries' => ['countries', 'country_translations', 'country_id'],
    'territory levels' => ['territory_levels', 'territory_level_translations', 'territory_level_id'],
    'territories' => ['territories', 'territory_translations', 'territory_id'],
    'object types' => ['object_types', 'object_type_translations', 'object_type_id'],
    'amenity groups' => ['amenity_groups', 'amenity_group_translations', 'amenity_group_id'],
    'amenities' => ['amenities', 'amenity_translations', 'amenity_id'],
    'contact channel types' => ['contact_channel_types', 'contact_channel_type_translations', 'contact_channel_type_id'],
]);

test('every content-bearing table has a translations sibling unique on (entity id, locale)', function (
    string $table,
    string $translationsTable,
    string $foreignKeyColumn,
): void {
    expect(Schema::hasTable($translationsTable))
        ->toBeTrue("Expected {$table} to have a sibling {$translationsTable} table.");

    expect(Schema::hasColumns($translationsTable, [$foreignKeyColumn, 'locale']))
        ->toBeTrue("Expected {$translationsTable} to carry {$foreignKeyColumn} and locale columns.");

    $uniqueIndexes = DB::select(
        "select indexdef from pg_indexes where tablename = ? and indexdef like 'CREATE UNIQUE%'",
        [$translationsTable]
    );

    $hasCompositeUnique = collect($uniqueIndexes)->contains(
        fn (object $row): bool => str_contains($row->indexdef, "({$foreignKeyColumn}, locale)")
    );

    expect($hasCompositeUnique)->toBeTrue(
        "Expected {$translationsTable} to have a unique index on ({$foreignKeyColumn}, locale)."
    );
})->with('content_bearing_tables');
