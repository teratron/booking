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
        // parent_id is staudenmeir/laravel-adjacency-list's expected column
        // name for HasRecursiveRelationships — the recursive CTE hierarchy
        // reads it directly, no materialized path maintained by hand.
        // country_id is denormalized here even though it is derivable by
        // walking to the root: scope queries and banner-targeting both
        // filter by country on every request, and a recursive walk per query
        // is the wrong cost for a field that is immutable in practice.
        //
        // Performance indexes (parent_id traversal, GiST on geom) are
        // deliberately not added here — that is the dedicated index-plan
        // task's job, once the full table set and real query shapes exist to
        // design against.
        Schema::create('territories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('territories')->nullOnDelete();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            $table->foreignId('level_id')->constrained('territory_levels')->cascadeOnDelete();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->geography('geom', subtype: 'point', srid: 4326)->nullable();
            $table->string('hero_image_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();

            // No soft-delete column: §5.6 blocks deletion outright while
            // objects or descendants are attached and uses is_active for
            // deactivation instead — a territory is never in an archived
            // state the way an object is.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('territories');
    }
};
