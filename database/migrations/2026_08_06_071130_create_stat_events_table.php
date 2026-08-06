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
        // Partitioned from creation — the highest-volume table in the
        // schema, and adding partitioning to a populated table later is a
        // migration nobody wants to run against production. Laravel's
        // Schema Builder cannot express PARTITION BY, so this table is
        // hand-written SQL rather than a Blueprint. Postgres requires the
        // partition key to be part of every unique constraint on a
        // partitioned table, hence the composite primary key
        // (id, occurred_at) rather than id alone.
        //
        // kind is coarse (six categories); for contact_click rows,
        // contact_channel_type_id disambiguates which contact method was
        // used — the finer-grained click types §3.1's prose lists (phone,
        // Viber, Telegram, …) are channel rows, not separate kinds.
        //
        // A single DEFAULT partition is created here, routing every row
        // regardless of date — the minimal shape this migration can
        // guarantee is always valid, whenever it runs. Splitting it into
        // real per-day or per-month partitions ahead of data arriving is a
        // scheduled job's concern, not a one-time migration's.
        DB::statement(<<<'SQL'
            create table stat_events (
                id bigserial not null,
                kind varchar(255) not null check (kind in (
                    'object_card_view', 'object_page_view', 'photo_view',
                    'contact_click', 'banner_impression', 'banner_click'
                )),
                subject_type varchar(255) not null,
                subject_id bigint not null,
                contact_channel_type_id bigint null references contact_channel_types(id) on delete set null,
                territory_id bigint null references territories(id) on delete set null,
                country_id bigint null references countries(id) on delete set null,
                locale varchar(10) null references languages(code) on delete set null,
                occurred_at timestamp(0) without time zone not null,
                dedup_token varchar(255) null,
                source_channel varchar(255) null check (source_channel in (
                    'direct', 'search', 'social', 'referral', 'internal', 'campaign'
                )),
                source_domain varchar(255) null,
                source_campaign varchar(255) null,
                primary key (id, occurred_at)
            ) partition by range (occurred_at)
        SQL);

        DB::statement('create index stat_events_subject_index on stat_events (subject_type, subject_id)');
        DB::statement('create index stat_events_territory_index on stat_events (territory_id)');
        DB::statement('create table stat_events_default partition of stat_events default');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('drop table if exists stat_events');
    }
};
