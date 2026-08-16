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
        Schema::create('error_pages', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('status_code')->unique();
            $table->timestamps();
        });

        Schema::create('error_page_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('error_page_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 10);
            $table->string('title');
            $table->text('body');
            // `ErrorPage` implements the `Translatable` contract, so
            // `TranslatableEntityRegistry`'s reflection-based discovery
            // sweeps it into `TranslationCompletenessReport` automatically —
            // the same generic reporting every other translatable entity in
            // this codebase goes through. That report queries `needs_review`
            // unconditionally, so the column is required here even though
            // an error page edit always applies immediately and never
            // actually enters review (`published_at` is likewise carried for
            // the same reason but never set by this table's own admin
            // screen).
            $table->boolean('needs_review')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->foreign('locale')->references('code')->on('languages')->cascadeOnDelete();
            $table->unique(['error_page_id', 'locale']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('error_page_translations');
        Schema::dropIfExists('error_pages');
    }
};
