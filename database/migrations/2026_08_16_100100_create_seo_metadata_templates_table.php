<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The middle rung of the metadata resolution ladder: an
     * administrator-defined title or description template per entity kind
     * and language, rendered from entity data when no explicit `seo_title`/
     * `seo_description` is set. `entity_type` and `field` are plain strings
     * rather than foreign keys — both are closed, code-level vocabularies,
     * not administrator-editable registries.
     */
    public function up(): void
    {
        Schema::create('seo_metadata_templates', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type');
            $table->string('locale', 10);
            $table->string('field');
            $table->string('template');
            $table->timestamps();

            $table->foreign('locale')->references('code')->on('languages')->cascadeOnDelete();
            $table->unique(['entity_type', 'locale', 'field']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_metadata_templates');
    }
};
