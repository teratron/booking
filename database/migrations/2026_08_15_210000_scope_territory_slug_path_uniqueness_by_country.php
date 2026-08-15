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
     *
     * `(locale, full_slug_path)` alone is not the real uniqueness boundary
     * either: two ROOT territories in different countries can carry the
     * identical name and therefore the identical (parent-free) full path —
     * "Centru" as a top-level territory in both Moldova and Georgia, say.
     * Those are different URLs (`/en/md/centru` vs `/en/ge/centru`, the
     * country segment lives outside `full_slug_path` entirely), so the
     * database must not reject the second insert. `country_id` is
     * denormalized onto the translation row — Postgres cannot express a
     * uniqueness scope through a foreign key's own parent table — kept in
     * sync by `TerritoryAdministrator::recomputeSlugPaths()`, the single
     * place that already writes `full_slug_path`.
     */
    public function up(): void
    {
        Schema::table('territory_translations', function (Blueprint $table) {
            $table->foreignId('country_id')->nullable()->after('territory_id')->constrained()->cascadeOnDelete();
        });

        DB::statement(<<<'SQL'
            update territory_translations tt
            set country_id = t.country_id
            from territories t
            where t.id = tt.territory_id
        SQL);

        Schema::table('territory_translations', function (Blueprint $table) {
            $table->foreignId('country_id')->nullable(false)->change();
            $table->dropUnique(['locale', 'full_slug_path']);
            $table->unique(['locale', 'country_id', 'full_slug_path']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('territory_translations', function (Blueprint $table) {
            $table->dropUnique(['locale', 'country_id', 'full_slug_path']);
            $table->unique(['locale', 'full_slug_path']);
            $table->dropConstrainedForeignId('country_id');
        });
    }
};
