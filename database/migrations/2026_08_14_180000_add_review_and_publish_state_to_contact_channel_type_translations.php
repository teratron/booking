<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The same gap earlier migrations closed for their own new tables,
     * recurring for the identical reason: `contact_channel_type_translations`
     * predates the `needs_review`/`published_at` convention and was never
     * brought into line, because nothing made `ContactChannelType` a
     * `Translatable` model until now. `TranslatableEntityRegistry`'s
     * reflection-based discovery picks up any new `Translatable` model the
     * moment it exists, and `TranslationCompletenessReport` would fail
     * querying a column that did not exist. Closing it here rather than
     * waiting for it to surface as a runtime crash.
     */
    public function up(): void
    {
        Schema::table('contact_channel_type_translations', function (Blueprint $blueprint): void {
            $blueprint->boolean('needs_review')->default(false);
            $blueprint->timestamp('published_at')->nullable();
        });

        // Mirrors every prior occurrence's own backfill: rows seeded before
        // this column existed were already live in every language they had
        // a row for.
        DB::table('contact_channel_type_translations')->whereNull('published_at')->update(['published_at' => DB::raw('created_at')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contact_channel_type_translations', function (Blueprint $blueprint): void {
            $blueprint->dropColumn(['needs_review', 'published_at']);
        });
    }
};
