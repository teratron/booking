<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The five entity-and-language-scoped fields beyond the
     * `seo_title`/`seo_description` pair every translation table already
     * carries: an explicit canonical-URL override, an explicit indexation
     * override, and the three Open Graph fields. Added to every
     * translation table for an entity kind that carries SEO data —
     * territories (covering every level of the hierarchy), object types,
     * objects, promotions, news items, and articles.
     *
     * Each table is migrated with its own literal `Schema::table()` call
     * rather than a loop over a table-name list — the static schema parser
     * this project's tooling relies on for model property inference only
     * recognizes a literal table-name argument, not one resolved through a
     * variable.
     */
    public function up(): void
    {
        Schema::table('territory_translations', function (Blueprint $table): void {
            $table->string('seo_canonical_url')->nullable();
            $table->boolean('seo_indexable')->nullable();
            $table->string('seo_og_title')->nullable();
            $table->string('seo_og_description')->nullable();
            $table->string('seo_og_image')->nullable();
        });

        Schema::table('object_type_translations', function (Blueprint $table): void {
            $table->string('seo_canonical_url')->nullable();
            $table->boolean('seo_indexable')->nullable();
            $table->string('seo_og_title')->nullable();
            $table->string('seo_og_description')->nullable();
            $table->string('seo_og_image')->nullable();
        });

        Schema::table('object_translations', function (Blueprint $table): void {
            $table->string('seo_canonical_url')->nullable();
            $table->boolean('seo_indexable')->nullable();
            $table->string('seo_og_title')->nullable();
            $table->string('seo_og_description')->nullable();
            $table->string('seo_og_image')->nullable();
        });

        Schema::table('promotion_translations', function (Blueprint $table): void {
            $table->string('seo_canonical_url')->nullable();
            $table->boolean('seo_indexable')->nullable();
            $table->string('seo_og_title')->nullable();
            $table->string('seo_og_description')->nullable();
            $table->string('seo_og_image')->nullable();
        });

        Schema::table('news_translations', function (Blueprint $table): void {
            $table->string('seo_canonical_url')->nullable();
            $table->boolean('seo_indexable')->nullable();
            $table->string('seo_og_title')->nullable();
            $table->string('seo_og_description')->nullable();
            $table->string('seo_og_image')->nullable();
        });

        Schema::table('article_translations', function (Blueprint $table): void {
            $table->string('seo_canonical_url')->nullable();
            $table->boolean('seo_indexable')->nullable();
            $table->string('seo_og_title')->nullable();
            $table->string('seo_og_description')->nullable();
            $table->string('seo_og_image')->nullable();
        });
    }

    public function down(): void
    {
        foreach ([
            'territory_translations',
            'object_type_translations',
            'object_translations',
            'promotion_translations',
            'news_translations',
            'article_translations',
        ] as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->dropColumn([
                    'seo_canonical_url',
                    'seo_indexable',
                    'seo_og_title',
                    'seo_og_description',
                    'seo_og_image',
                ]);
            });
        }
    }
};
