<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The same gap `2026_08_12_100000_add_review_and_publish_state_to_placement_translation_tables`
     * closed for that pass's own new tables, recurring for exactly the
     * reason its own docblock predicted: `room_translations` predates the
     * `needs_review`/`published_at` convention and was never brought into
     * line, because nothing made `Room` a `Translatable` model until now.
     * `TranslatableEntityRegistry`'s reflection-based discovery picked it up
     * the moment the model existed, and `TranslationCompletenessReport`
     * failed with the same `SQLSTATE[42703]` querying a column that did not
     * exist. Closing it here rather than waiting for the next table to
     * rediscover it the same way.
     */
    public function up(): void
    {
        Schema::table('room_translations', function (Blueprint $blueprint): void {
            $blueprint->boolean('needs_review')->default(false);
            $blueprint->timestamp('published_at')->nullable();
        });

        // Mirrors both prior migrations' own backfill: rows seeded before
        // this column existed were already live in every language they had
        // a row for.
        DB::table('room_translations')->whereNull('published_at')->update(['published_at' => DB::raw('created_at')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('room_translations', function (Blueprint $blueprint): void {
            $blueprint->dropColumn(['needs_review', 'published_at']);
        });
    }
};
