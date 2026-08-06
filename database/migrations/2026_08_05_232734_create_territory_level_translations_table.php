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
        Schema::create('territory_level_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('territory_level_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 10);
            $table->string('singular_name');
            $table->string('plural_name');
            $table->timestamps();

            $table->foreign('locale')->references('code')->on('languages')->cascadeOnDelete();
            $table->unique(['territory_level_id', 'locale']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('territory_level_translations');
    }
};
