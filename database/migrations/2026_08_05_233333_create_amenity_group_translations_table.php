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
        // An owner-facing group label ("catering", "accessibility") is
        // content-bearing text like any other in a multi-language portal —
        // every active language needs its own version, not just the
        // portal's primary one.
        Schema::create('amenity_group_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('amenity_group_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 10);
            $table->string('name');
            $table->timestamps();

            $table->foreign('locale')->references('code')->on('languages')->cascadeOnDelete();
            $table->unique(['amenity_group_id', 'locale']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('amenity_group_translations');
    }
};
