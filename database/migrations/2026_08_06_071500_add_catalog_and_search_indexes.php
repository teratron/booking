<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Every specialized index below (partial, GIN, GiST, trigram operator
        // class, explicit DESC ordering) falls outside Blueprint's fluent
        // API, so the whole set is raw SQL for one consistent, auditable
        // shape rather than a mix of fluent and raw calls.
        //
        // The composite (country_id, territory_id, object_type_id, status)
        // also serves the plain "country + territory" lookup, since a
        // leading prefix of a composite btree index answers a query on its
        // first columns alone — no separate objects(country_id, territory_id)
        // index is needed alongside it.
        DB::statement(
            'create index objects_scope_ordering_index on objects '.
            '(country_id, territory_id, object_type_id, status)'
        );
        DB::statement('create index objects_object_type_id_index on objects (object_type_id)');

        // Published, non-deleted objects are the overwhelming majority of
        // catalog reads; a partial index keeps it small and keeps every
        // soft-deleted or draft row out of the index the hottest query scans.
        DB::statement(
            'create index objects_status_published_index on objects (status) where deleted_at is null'
        );

        // Filterable, type-declared attributes live in this JSONB bag rather
        // than as typed columns; GIN is what makes a containment query
        // (attributes @> '{"key": "value"}') sub-linear instead of a full
        // table scan decoding JSON row by row.
        DB::statement('create index objects_attributes_gin_index on objects using gin (attributes)');

        // Map bbox, radius, and nearby-object queries need a spatial index
        // on both ends: the object being searched for and the territory
        // used to centre the search.
        DB::statement('create index objects_geom_gist_index on objects using gist (geom)');
        DB::statement('create index territories_geom_gist_index on territories using gist (geom)');

        // Descendant expansion is the hot path of every territory page and
        // every catalog view scoped below the country level; a recursive
        // CTE walking parent_id needs this index to avoid a sequential scan
        // at every recursion step.
        DB::statement('create index territories_parent_id_index on territories (parent_id)');

        DB::statement('create index object_placements_package_index on object_placements (placement_package_id)');
        DB::statement('create index object_placements_ends_at_index on object_placements (ends_at)');

        DB::statement('create index moderation_requests_decision_created_index on moderation_requests (decision, created_at)');

        // Descending occurred_at because the catalog's ordering contract
        // reads the *most recent* bump for the scope being rendered — an
        // ascending index would still work but forces a backward scan on
        // every read of the hottest query in the schema.
        DB::statement(
            'create index bump_events_object_scope_occurred_index on bump_events '.
            '(object_id, scope_type, scope_id, occurred_at desc)'
        );

        // Trigram, not a plain btree, because object name search is
        // substring/typo-tolerant ("bukovel" should surface "Hotel
        // Bukovel"), which a prefix-only btree cannot answer.
        DB::statement(
            'create index object_translations_name_trgm_index on object_translations '.
            'using gin (name gin_trgm_ops)'
        );

        DB::statement('create index articles_publish_at_index on articles (publish_at)');
        DB::statement('create index news_items_publish_at_index on news_items (publish_at)');
        DB::statement('create index promotions_starts_ends_index on promotions (starts_at, ends_at)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('drop index if exists objects_scope_ordering_index');
        DB::statement('drop index if exists objects_object_type_id_index');
        DB::statement('drop index if exists objects_status_published_index');
        DB::statement('drop index if exists objects_attributes_gin_index');
        DB::statement('drop index if exists objects_geom_gist_index');
        DB::statement('drop index if exists territories_geom_gist_index');
        DB::statement('drop index if exists territories_parent_id_index');
        DB::statement('drop index if exists object_placements_package_index');
        DB::statement('drop index if exists object_placements_ends_at_index');
        DB::statement('drop index if exists moderation_requests_decision_created_index');
        DB::statement('drop index if exists bump_events_object_scope_occurred_index');
        DB::statement('drop index if exists object_translations_name_trgm_index');
        DB::statement('drop index if exists articles_publish_at_index');
        DB::statement('drop index if exists news_items_publish_at_index');
        DB::statement('drop index if exists promotions_starts_ends_index');
    }
};
