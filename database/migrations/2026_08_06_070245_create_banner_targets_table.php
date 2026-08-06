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
        // One banner may target several territories, categories, and
        // languages at once, each independently. A single polymorphic pivot
        // unifies the three dimensions — target_type distinguishes
        // Territory, ObjectType (category), and Language — rather than
        // three separate pivot tables for what is the same "which nodes is
        // this banner eligible on" relationship. An untargeted dimension
        // means "all", so no rows for a dimension is a valid, common state.
        Schema::create('banner_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('banner_id')->constrained()->cascadeOnDelete();
            $table->morphs('target');
            $table->timestamps();

            $table->unique(['banner_id', 'target_type', 'target_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('banner_targets');
    }
};
