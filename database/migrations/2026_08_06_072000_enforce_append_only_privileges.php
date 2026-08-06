<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `audits` and `financial_records` are the two tables the retention
     * rules mark "never deleted by any role" — stronger language than the
     * "chief administrator only" rule given to every other append-only
     * table. A GRANT/REVOKE scheme cannot deliver that: the application
     * connects as this project's single Postgres role, which is also the
     * cluster's bootstrap superuser and the owner of every table it
     * creates, and Postgres lets neither an owner nor a superuser be
     * restricted by REVOKE — both bypass every privilege check
     * unconditionally, confirmed by attempting exactly that and reading
     * the resulting "the bootstrap superuser must have the SUPERUSER
     * attribute" error. A trigger has no such escape hatch: it fires for
     * every row-level UPDATE/DELETE regardless of who is connected, so it
     * is the only mechanism in this schema that actually delivers "never,
     * by any role."
     *
     * `CREATE OR REPLACE` throughout: `php artisan migrate:fresh` drops
     * tables but not standalone functions or triggers, confirmed by a
     * plain `CREATE FUNCTION` failing with "already exists" on a second
     * fresh run against the same database — replace, not create-or-fail.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION enforce_append_only() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION
                    'table % is append-only: % is never permitted', TG_TABLE_NAME, TG_OP
                    USING ERRCODE = 'insufficient_privilege';
            END;
            $$ LANGUAGE plpgsql;
            SQL);

        DB::statement(<<<'SQL'
            CREATE OR REPLACE TRIGGER audits_append_only
                BEFORE UPDATE OR DELETE ON audits
                FOR EACH ROW EXECUTE FUNCTION enforce_append_only();
            SQL);

        DB::statement(<<<'SQL'
            CREATE OR REPLACE TRIGGER financial_records_append_only
                BEFORE UPDATE OR DELETE ON financial_records
                FOR EACH ROW EXECUTE FUNCTION enforce_append_only();
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('drop trigger if exists audits_append_only on audits');
        DB::statement('drop trigger if exists financial_records_append_only on financial_records');
        DB::statement('drop function if exists enforce_append_only()');
    }
};
