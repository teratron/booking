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
        Schema::create('placement_tier_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('placement_tier_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 10);
            $table->string('label');
            $table->string('badge_text');
            $table->timestamps();

            $table->foreign('locale')->references('code')->on('languages')->cascadeOnDelete();
            $table->unique(['placement_tier_id', 'locale']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('placement_tier_translations');
    }
};
