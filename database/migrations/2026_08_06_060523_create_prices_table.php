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
        // Period-aware records, not a single number — a price applies
        // directly to an object (a restaurant's average cheque) or to one
        // of its rooms, hence the polymorphic target rather than two
        // nullable foreign keys. calculation_unit is the per-room/per-
        // person/per-night/per-service/"from" distinction; type is a
        // separate, free-form label ("standard", "weekend", "high season")
        // an owner sets to distinguish concurrent rates for the same unit.
        Schema::create('prices', function (Blueprint $table) {
            $table->id();
            $table->morphs('priceable');
            $table->string('type')->nullable();
            $table->enum('calculation_unit', ['per_room', 'per_person', 'per_night', 'per_service', 'from']);
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3);
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prices');
    }
};
