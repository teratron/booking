<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Catalog & Search Index Plan
|--------------------------------------------------------------------------
|
| Every row of the index plan has a corresponding index — enumerated
| explicitly rather than checked by eye, since a missing index here means
| the catalog's hottest query silently degrades to a sequential scan the
| first time the table holds real volume. Structural predicates (partial
| WHERE, DESC ordering, the trigram operator class) are asserted from the
| index definition itself, not assumed from the migration file.
|
*/

dataset('required_indexes', [
    'catalog scope + ordering composite' => ['objects', 'objects_scope_ordering_index'],
    'object type' => ['objects', 'objects_object_type_id_index'],
    'publication status (partial)' => ['objects', 'objects_status_published_index'],
    'filterable attributes (GIN)' => ['objects', 'objects_attributes_gin_index'],
    'object spatial (GiST)' => ['objects', 'objects_geom_gist_index'],
    'territory spatial (GiST)' => ['territories', 'territories_geom_gist_index'],
    'territory parent (CTE traversal)' => ['territories', 'territories_parent_id_index'],
    'placement package lookup' => ['object_placements', 'object_placements_package_index'],
    'placement package expiry' => ['object_placements', 'object_placements_ends_at_index'],
    'moderation status' => ['moderation_requests', 'moderation_requests_decision_created_index'],
    'bump date' => ['bump_events', 'bump_events_object_scope_occurred_index'],
    'object name (trigram)' => ['object_translations', 'object_translations_name_trgm_index'],
    'article publication date' => ['articles', 'articles_publish_at_index'],
    'news publication date' => ['news_items', 'news_items_publish_at_index'],
    'promotion validity window' => ['promotions', 'promotions_starts_ends_index'],
]);

test('every row of the index plan has a corresponding index', function (string $table, string $indexName): void {
    $exists = DB::select(
        'select 1 from pg_indexes where tablename = ? and indexname = ?',
        [$table, $indexName]
    );

    expect($exists)->not->toBeEmpty("Expected {$table} to carry index {$indexName}.");
})->with('required_indexes');

test('the publication status index is partial on non-deleted rows', function (): void {
    $def = DB::selectOne("select indexdef from pg_indexes where indexname = 'objects_status_published_index'");

    expect($def->indexdef)->toContain('WHERE (deleted_at IS NULL)');
});

test('the bump date index orders occurred_at most-recent-first', function (): void {
    $def = DB::selectOne("select indexdef from pg_indexes where indexname = 'bump_events_object_scope_occurred_index'");

    expect($def->indexdef)->toContain('occurred_at DESC');
});

test('the object name index uses the trigram operator class', function (): void {
    $def = DB::selectOne("select indexdef from pg_indexes where indexname = 'object_translations_name_trgm_index'");

    expect($def->indexdef)->toContain('gin_trgm_ops');
});

test('every translation table with a slug carries a unique index on (locale, slug), and every translation table on (entity id, locale)', function (): void {
    // territory_translations is the one deliberate exception: two
    // territories under different parents (or different countries
    // entirely) may legitimately share a leaf slug or even an identical
    // full path — "Centru" as a root territory in both Moldova and
    // Georgia, say — so its real uniqueness boundary is the full
    // hierarchical path scoped by country, not the bare slug. The index is
    // composite (`locale, country_id, full_slug_path`), so the check below
    // matches on the trailing column rather than an exact two-column shape.
    $slugUniqueColumnByTable = [
        'territory_translations' => 'full_slug_path',
    ];

    $translationTables = collect(DB::select(
        "select table_name from information_schema.tables where table_schema = 'public' and table_name like '%\\_translations'"
    ))->pluck('table_name');

    expect($translationTables)->not->toBeEmpty();

    foreach ($translationTables as $table) {
        $uniqueDefs = collect(DB::select(
            "select indexdef from pg_indexes where tablename = ? and indexdef like 'CREATE UNIQUE%'",
            [$table]
        ))->pluck('indexdef');

        $hasLocaleUnique = $uniqueDefs->contains(fn (string $def): bool => str_contains($def, ', locale)'));
        expect($hasLocaleUnique)->toBeTrue("Expected {$table} to carry a unique index ending in (..., locale).");

        if (Schema::hasColumn($table, 'slug')) {
            $slugColumn = $slugUniqueColumnByTable[$table] ?? 'slug';
            // Matches both the plain two-column shape ("(locale, slug)")
            // and territory_translations' composite one ("(locale,
            // country_id, full_slug_path)") — either way, the trailing
            // column before the closing paren is what matters.
            $hasSlugUnique = $uniqueDefs->contains(fn (string $def): bool => str_contains($def, ", {$slugColumn})"));
            expect($hasSlugUnique)->toBeTrue("Expected {$table} to carry a unique index on (locale, ..., {$slugColumn}).");
        }
    }
});

test('stat_dailies rolls up by date, subject, and kind as a usable index prefix', function (): void {
    $def = DB::selectOne("select indexdef from pg_indexes where indexname = 'stat_dailies_unique_rollup'");

    expect($def->indexdef)->toContain('(date, subject_type, subject_id, kind,');
});
