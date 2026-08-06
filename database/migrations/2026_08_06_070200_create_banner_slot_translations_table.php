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
        // Slots are administrator-manageable data (an operator may add a new
        // inventory position without a deployment), so the display name is
        // itself translated data rather than a static Filament label key.
        Schema::create('banner_slot_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('banner_slot_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 10);
            $table->string('name');
            $table->timestamps();

            $table->foreign('locale')->references('code')->on('languages')->cascadeOnDelete();
            $table->unique(['banner_slot_id', 'locale']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('banner_slot_translations');
    }
};
