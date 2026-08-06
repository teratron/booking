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
        // Desktop and mobile creatives attach through the polymorphic media
        // table via named collections, the same mechanism objects use for
        // photographs — not dedicated path columns, so the portal gains
        // conversions and queued optimization for free. Territory,
        // category, and language targeting live in banner_targets; name is
        // internal (administrator-facing only), so it is not translated.
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('banner_slot_id')->constrained();
            $table->string('name');
            $table->string('advertiser');
            $table->string('destination_link');
            $table->unsignedInteger('display_order')->default(0);
            $table->date('starts_at');
            $table->date('ends_at');
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
