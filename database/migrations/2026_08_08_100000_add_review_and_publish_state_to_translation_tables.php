<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every `*_translations` table this project has shipped so far — new
     * translatable models add their own row here as they arrive, the same
     * way `TranslationTableSchemaTest` enumerates them.
     *
     * @var list<string>
     */
    private const array TRANSLATION_TABLES = [
        'amenity_translations',
        'amenity_group_translations',
        'country_translations',
        'module_translations',
        'object_type_translations',
        'object_translations',
        'promotion_label_translations',
        'territory_translations',
        'territory_level_translations',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::TRANSLATION_TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                // `needs_review`: false for a translation an administrator
                // wrote directly, true for one a copy-from-primary action
                // produced — the completeness report and the SEO warning it
                // feeds must not count copied-but-unreviewed text as done.
                $blueprint->boolean('needs_review')->default(false);
                // `published_at`: this language version's own publish state,
                // independent of the entity's overall moderation status —
                // an object can be published in English while its Russian
                // translation is still being reviewed.
                $blueprint->timestamp('published_at')->nullable();
            });

            // Existing rows predate this column and were already live in
            // every language they had a row for — backfilling published_at
            // to created_at preserves that, rather than un-publishing
            // everything the moment this migration runs.
            DB::table($table)->whereNull('published_at')->update(['published_at' => DB::raw('created_at')]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (self::TRANSLATION_TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropColumn(['needs_review', 'published_at']);
            });
        }
    }
};
