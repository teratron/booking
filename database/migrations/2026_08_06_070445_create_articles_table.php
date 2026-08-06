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
        // Administrator-authored only — articles never enter the moderation
        // queue, since an administrator publishing is already the trusted
        // act; hence no moderation_status column here, unlike news_items
        // and promotions. Cover image and any inline media attach through
        // the polymorphic media table, matching objects and banners.
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->constrained('users');
            $table->foreignId('article_category_id')->nullable()->constrained();
            $table->timestamp('publish_at')->nullable();
            $table->enum('status', ['draft', 'scheduled', 'published']);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
