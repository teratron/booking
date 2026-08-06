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
        // Owner-authored, always object-scoped, and always territory-scoped
        // — unlike news_items, both object_id and territory_id are
        // required, not optional. Archives automatically when its end date
        // passes; the transition is a scheduled job, not a render-time
        // check, hence a persisted status value rather than a computed one.
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('object_id')->constrained()->cascadeOnDelete();
            $table->foreignId('territory_id')->constrained();
            $table->date('starts_at');
            $table->date('ends_at');
            $table->enum('status', ['draft', 'scheduled', 'published', 'archived']);
            $table->enum('moderation_status', ['pending', 'approved', 'rejected', 'revision_requested'])->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
