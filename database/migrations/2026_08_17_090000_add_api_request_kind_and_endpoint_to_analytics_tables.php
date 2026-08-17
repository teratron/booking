<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // A seventh kind — API request consumption — alongside the six the
        // event model shipped with. `subject_type`/`subject_id` extend to
        // cover ApiToken the same way they already cover Object/Banner;
        // `endpoint` is new, since nothing existing names which route a row
        // concerns. Both tables' CHECK constraints on `kind` are widened by
        // name, confirmed against the actual constraint Postgres generated
        // for each (`{table}_kind_check`) — `stat_events` stays partitioned,
        // so the constraint is altered at the parent table and Postgres
        // propagates it to every partition automatically.
        DB::statement('alter table stat_events drop constraint stat_events_kind_check');
        DB::statement(<<<'SQL'
            alter table stat_events add constraint stat_events_kind_check check (kind in (
                'object_card_view', 'object_page_view', 'photo_view',
                'contact_click', 'banner_impression', 'banner_click', 'api_request'
            ))
            SQL);
        DB::statement('alter table stat_events add column endpoint varchar(255) null');

        DB::statement('alter table stat_dailies drop constraint stat_dailies_kind_check');
        DB::statement(<<<'SQL'
            alter table stat_dailies add constraint stat_dailies_kind_check check (kind in (
                'object_card_view', 'object_page_view', 'photo_view',
                'contact_click', 'banner_impression', 'banner_click', 'api_request'
            ))
            SQL);

        // The rollup's own dedup key must widen alongside the new column —
        // two API-request rows for different endpoints are not the same
        // aggregate row.
        Schema::table('stat_dailies', function (Blueprint $table): void {
            $table->string('endpoint')->nullable();
            $table->dropUnique('stat_dailies_unique_rollup');
            $table->unique(
                ['date', 'subject_type', 'subject_id', 'kind', 'contact_channel_type_id', 'territory_id', 'locale', 'endpoint'],
                'stat_dailies_unique_rollup'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stat_dailies', function (Blueprint $table): void {
            $table->dropUnique('stat_dailies_unique_rollup');
            $table->unique(
                ['date', 'subject_type', 'subject_id', 'kind', 'contact_channel_type_id', 'territory_id', 'locale'],
                'stat_dailies_unique_rollup'
            );
            $table->dropColumn('endpoint');
        });

        DB::statement('alter table stat_dailies drop constraint stat_dailies_kind_check');
        DB::statement(<<<'SQL'
            alter table stat_dailies add constraint stat_dailies_kind_check check (kind in (
                'object_card_view', 'object_page_view', 'photo_view',
                'contact_click', 'banner_impression', 'banner_click'
            ))
            SQL);

        DB::statement('alter table stat_events drop column endpoint');
        DB::statement('alter table stat_events drop constraint stat_events_kind_check');
        DB::statement(<<<'SQL'
            alter table stat_events add constraint stat_events_kind_check check (kind in (
                'object_card_view', 'object_page_view', 'photo_view',
                'contact_click', 'banner_impression', 'banner_click'
            ))
            SQL);
    }
};
