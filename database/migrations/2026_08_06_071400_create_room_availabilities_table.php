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
        // Dormant booking module (disabled by default — governed by the
        // `modules` registry, not this migration). A sparse table: absence
        // of a row means the date follows the room's default open state.
        // Materializing every date for every room across a three-country
        // catalog would be the wrong cost for data that is overwhelmingly
        // "open". This table exists in the schema and carries no rows until
        // the booking module is activated for at least one object.
        Schema::create('room_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->enum('state', ['open', 'blocked']);
            $table->decimal('rate_override', 10, 2)->nullable();
            $table->unsignedInteger('minimum_stay')->nullable();
            $table->timestamps();

            $table->unique(['room_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('room_availabilities');
    }
};
