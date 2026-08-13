<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every `*_translations` table this project has shipped so far that was
     * missing from `2026_08_08_100000_add_review_and_publish_state_to_translation_tables`'s
     * own list — its author enumerated the nine tables that existed at the
     * time; the placement/advertising/notifications/content-publishing
     * tables were part of the same early schema pass but were never added
     * to that list.
     *
     * The gap is not hypothetical: it surfaced as a real `SQLSTATE[42703]`
     * the moment `TranslatableEntityRegistry`'s reflection-based discovery
     * picked up the new `PlacementTier`/`PlacementPackage` models and
     * `TranslationCompletenessReport` queried their translation table for a
     * `needs_review` column that did not exist. Rather than fix that one
     * pair and rediscover the same gap once each later track's models
     * arrive, every remaining table this phase's tracks will make
     * `Translatable` is brought into line here, in one pass. A table listed
     * here whose model turns out not to need translation completeness
     * tracking carries two unused nullable/defaulted columns — harmless —
     * rather than a second silent gap.
     *
     * @var list<string>
     */
    private const array TRANSLATION_TABLES = [
        'placement_tier_translations',
        'placement_package_translations',
        'notification_channel_translations',
        'banner_slot_translations',
        'banner_translations',
        'article_category_translations',
        'article_translations',
        'news_translations',
        'promotion_translations',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::TRANSLATION_TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->boolean('needs_review')->default(false);
                $blueprint->timestamp('published_at')->nullable();
            });

            // Mirrors the original migration's backfill: rows seeded before
            // this column existed (the four launch tiers/packages) were
            // already live in every language they had a row for.
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
