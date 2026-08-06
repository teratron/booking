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
        // Filter facets on the catalog are the subset of this registry
        // flagged is_filterable — not a fixed list ([TZ] §110). Adding a
        // filterable amenity is a data operation, never a code change.
        Schema::create('amenities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('amenity_group_id')->constrained()->cascadeOnDelete();
            $table->string('icon_path')->nullable();
            $table->boolean('is_filterable')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('amenities');
    }
};
