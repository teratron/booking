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
        // An article may relate to several objects, unlike news and
        // promotions, which carry a single optional owner association —
        // this asymmetry is the source specification's, not invented here.
        Schema::create('article_object', function (Blueprint $table) {
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('object_id')->constrained()->cascadeOnDelete();

            $table->primary(['article_id', 'object_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('article_object');
    }
};
