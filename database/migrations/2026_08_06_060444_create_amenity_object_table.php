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
        // The owner selects from the registry; they do not invent entries —
        // this pivot is the selection. Only amenities from groups applicable
        // to the object's own type are offered, per the taxonomy batch's
        // amenity_group_object_type pivot.
        Schema::create('amenity_object', function (Blueprint $table) {
            $table->foreignId('amenity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('object_id')->constrained()->cascadeOnDelete();

            $table->primary(['amenity_id', 'object_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('amenity_object');
    }
};
