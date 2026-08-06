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
        // Accommodation types only, gated at the application layer by the
        // object type's has_rooms flag — the schema itself does not enforce
        // that constraint. No occupancy calendar exists in the default
        // configuration: this table has no date columns at all.
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('object_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('capacity')->nullable();
            $table->unsignedInteger('room_count')->nullable();
            $table->decimal('area_sqm', 8, 2)->nullable();
            $table->string('bed_configuration')->nullable();
            $table->unsignedInteger('max_guests')->nullable();
            $table->boolean('has_extra_bed')->default(false);
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
