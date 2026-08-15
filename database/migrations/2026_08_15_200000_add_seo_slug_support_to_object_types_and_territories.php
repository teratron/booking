<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Two corrections the URL grammar needs before any route can resolve
     * through it. First, `object_type_translations` has never carried a
     * slug — the typed-catalog segment of the grammar
     * (`/{settlement}/{type}`) needs one per language, so it is added here
     * and backfilled from the existing name (a handful of rows; no
     * hierarchy to walk, unlike territories).
     *
     * Second, `territory_translations`' original `unique(['locale', 'slug'])`
     * constraint was already flagged as an approximation at creation time —
     * two territories in different countries may legitimately share a leaf
     * slug (e.g. a district named "Centru" under two different regions),
     * and a flat slug-only uniqueness rejects that. The real uniqueness
     * boundary is the full hierarchical path, so this drops the bare
     * constraint and replaces it with one on `(locale, full_slug_path)` —
     * the column the resolver actually looks up. Postgres does not treat
     * NULLs as equal for uniqueness, so this is safe to add before the
     * backfill in `TerritoryAdministrator` populates every row.
     */
    public function up(): void
    {
        Schema::table('object_type_translations', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('name');
        });

        foreach (DB::table('object_type_translations')->select(['id', 'name'])->get() as $row) {
            DB::table('object_type_translations')->where('id', $row->id)->update([
                'slug' => Str::slug((string) $row->name),
            ]);
        }

        Schema::table('object_type_translations', function (Blueprint $table) {
            $table->string('slug')->nullable(false)->change();
            $table->unique(['locale', 'slug']);
        });

        Schema::table('territory_translations', function (Blueprint $table) {
            $table->dropUnique(['locale', 'slug']);
            $table->unique(['locale', 'full_slug_path']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('territory_translations', function (Blueprint $table) {
            $table->dropUnique(['locale', 'full_slug_path']);
            $table->unique(['locale', 'slug']);
        });

        Schema::table('object_type_translations', function (Blueprint $table) {
            $table->dropUnique(['locale', 'slug']);
            $table->dropColumn('slug');
        });
    }
};
