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
        // Dormant booking module — see room_availabilities. Portal- or
        // country-level activation makes the capability available; it does
        // not enroll anyone's object automatically. enabled_by_owner is
        // that per-object opt-in, defaulting false even for an object whose
        // type supports rooms.
        Schema::create('booking_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('object_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('enabled_by_owner')->default(false);
            $table->unsignedInteger('response_window_hours')->nullable();
            $table->unsignedInteger('checkout_hold_window_minutes')->nullable();
            $table->text('cancellation_policy')->nullable();
            $table->unsignedInteger('advance_booking_horizon_days')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_settings');
    }
};
