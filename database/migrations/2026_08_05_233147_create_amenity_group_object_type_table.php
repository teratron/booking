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
        // Each object type declares which amenity groups apply to it — a
        // restaurant offers catering amenities, not room amenities. This
        // pivot is what the owner-cabinet service-selection screen filters
        // against, so only the groups relevant to the object's type appear.
        Schema::create('amenity_group_object_type', function (Blueprint $table) {
            $table->foreignId('amenity_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('object_type_id')->constrained()->cascadeOnDelete();

            $table->primary(['amenity_group_id', 'object_type_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('amenity_group_object_type');
    }
};
