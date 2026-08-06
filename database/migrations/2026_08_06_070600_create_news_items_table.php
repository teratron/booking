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
        // Owner- or administrator-authored; may be object-scoped or
        // portal-wide — object_id is nullable for exactly that reason.
        // moderation_status is nullable because administrator-authored
        // news never enters the queue, matching the same convention already
        // established on objects.
        Schema::create('news_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->constrained('users');
            $table->foreignId('object_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('territory_id')->nullable()->constrained();
            $table->foreignId('article_category_id')->nullable()->constrained();
            $table->timestamp('publish_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->boolean('is_pinned')->default(false);
            $table->enum('status', ['draft', 'scheduled', 'published', 'withdrawn']);
            $table->enum('moderation_status', ['pending', 'approved', 'rejected', 'revision_requested'])->nullable();
            $table->unsignedBigInteger('view_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('news_items');
    }
};
