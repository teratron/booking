<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Slugs are per language and stable; changing one creates a
        // permanent redirect from the old path, never a silent reuse.
        // Covers slug changes, territory reparenting, merged duplicate
        // objects, and archived content — all resolve through the same
        // (language, from_path) lookup on a sitemap/search miss.
        Schema::create('redirects', function (Blueprint $table) {
            $table->id();
            $table->string('locale', 10);
            $table->string('from_path');
            $table->string('to_path');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('locale')->references('code')->on('languages')->cascadeOnDelete();
            $table->unique(['locale', 'from_path']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('redirects');
    }
};
